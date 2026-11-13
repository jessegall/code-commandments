package hooks

import (
	"errors"
	"slices"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/walk"
)

// judgedWhole is how a hand-in and a merge are judged: the checkout read as it is judged, through the one cache every
// checkout shares, every rule asked, and the marks kept that land on the lines each named file changed. A rule that
// reads across files sees the whole tree, and an unchanged file costs a cache read.
// It says false when the checkout could not be read, so nothing was asked.
func judgedWhole(checkout string, project config.Config, rules []detectors.Detector, rulesFor func(string) []detectors.Detector,
	files []string, linesOf func(string) git.ChangedLines, marking Marking) ([]SinMark, judgedTimes, bool) {
	if len(files) == 0 {
		return nil, judgedTimes{}, true
	}
	reading, keptBefore, waitedBefore := time.Now(), engine.Counted().Kept, scan.ReadingWaited()
	codebase, err := readWhole(checkout, project, files)
	var incomplete scan.Incomplete
	if codebase == nil || (err != nil && !errors.As(err, &incomplete)) {
		return nil, judgedTimes{read: time.Since(reading)}, false
	}
	times := judgedTimes{files: len(codebase.Files()), asked: len(rules), kept: int(engine.Counted().Kept - keptBefore), read: time.Since(reading),
		waited: scan.ReadingWaited() - waitedBefore}
	judging := time.Now()

	held := map[string]string{}
	for _, file := range files {
		held[file] = walk.Mark(file)
	}
	parsed.keeps(codebase.Streams(), held)

	asked := map[string]map[string]bool{}
	lines := map[string]git.ChangedLines{}
	reported := codebase.Reporting(files...)
	var marks []SinMark
	for _, rule := range rules {
		for _, match := range safely(rule, reported) {
			file := match.File()
			if !slices.Contains(files, file) || !askedOf(asked, rulesFor, file)[detectors.IdentityOf(rule)] {
				continue
			}
			if _, read := lines[file]; !read {
				lines[file] = linesOf(file)
			}
			if !lines[file].Covers(match.Line()) {
				continue
			}
			if mark := marking.Of(rule, match, lines[file]); !mark.IsFiled() {
				marks = append(marks, mark)
			}
		}
	}

	times.rules = time.Since(judging)

	return marks, times, true
}

// judgedTimes is what a whole judging spent: the files it read and how many of them the cache served, the time
// reading them and the part of it spent waiting for another run reading the same checkout, and the time its rules took, and how many rules it asked.
type judgedTimes struct {
	files  int
	asked  int
	kept   int
	read   time.Duration
	waited time.Duration
	rules  time.Duration
}

// readWhole reads a checkout as judge reads it: its configured roots, without what it excludes, in the languages it
// reads that the changed files reach. A test replaces it to make the read fail.
var readWhole = func(checkout string, project config.Config, changed []string) (*engine.Codebase, error) {
	roots, err := project.WalkedRoots(checkout)
	if err != nil || len(roots) == 0 {
		roots = []string{checkout}
	}

	return scan.Walk(roots, project.Exclusions(checkout)).Only(source.Reaching(changed, reads(checkout, project))...).Load()
}

// askedOf is the rules a file is judged by, by identity, worked out once a file.
func askedOf(asked map[string]map[string]bool, rulesFor func(string) []detectors.Detector, file string) map[string]bool {
	if known, held := asked[file]; held {
		return known
	}
	known := map[string]bool{}
	for _, rule := range rulesFor(file) {
		known[detectors.IdentityOf(rule)] = true
	}
	asked[file] = known

	return known
}
