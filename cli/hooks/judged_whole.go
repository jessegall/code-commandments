package hooks

import (
	"errors"
	"slices"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/walk"
)

// judgedWhole is how a hand-in and a merge are judged: the checkout read as it is judged, through the one cache every
// checkout shares, every rule asked, and the marks kept that land on the lines each named file changed. A rule that
// reads across files sees the whole tree, and an unchanged file costs a cache read.
// It says false when the checkout could not be read, so nothing was asked.
func judgedWhole(checkout string, project config.Config, rules []detectors.Detector, rulesFor func(string) []detectors.Detector,
	files []string, linesOf func(string) git.ChangedLines, marking Marking) ([]SinMark, bool) {
	codebase, err := readWhole(checkout, project)
	var incomplete scan.Incomplete
	if codebase == nil || (err != nil && !errors.As(err, &incomplete)) {
		return nil, false
	}

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

	return marks, true
}

// readWhole reads a checkout as judge reads it: its configured roots, without what it excludes, in the languages it
// reads. A test replaces it to make the read fail.
var readWhole = func(checkout string, project config.Config) (*engine.Codebase, error) {
	roots, err := project.WalkedRoots(checkout)
	if err != nil || len(roots) == 0 {
		roots = []string{checkout}
	}

	return scan.Walk(roots, project.Exclusions(checkout)).Only(reads(checkout, project)...).Load()
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
