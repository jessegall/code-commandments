package story

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
)

func (s *Story) Says(ran Ran, texts ...string) {
	s.t.Helper()

	for _, text := range texts {
		if !strings.Contains(ran.Text(), text) {
			s.Fails(fmt.Sprintf("the answer does not say %q; it says:\n%s", text, ran.Text()))
		}
	}
}

func (s *Story) SaysOnce(ran Ran, text string) {
	s.t.Helper()

	if found := strings.Count(ran.Text(), text); found != 1 {
		s.Fails(fmt.Sprintf("the answer says %q %d times, where it says it once; it says:\n%s", text, found, ran.Text()))
	}
}

func (s *Story) Never(ran Ran, texts ...string) {
	s.t.Helper()

	for _, text := range texts {
		if strings.Contains(ran.Text(), text) {
			s.Fails(fmt.Sprintf("the answer says %q, which it must not; it says:\n%s", text, ran.Text()))
		}
	}
}

func (s *Story) Names(ran Ran, file, sin string) {
	s.t.Helper()

	s.Says(ran, file, sin)
}

func (s *Story) Exits(ran Ran, code int) {
	s.t.Helper()

	if ran.Code != code {
		s.Fails(fmt.Sprintf("the command exited with %d, where %d was meant; it says:\n%s", ran.Code, code, ran.Text()))
	}
}

func (s *Story) Refuses(ran Ran) {
	s.t.Helper()

	if ran.Code == 0 {
		s.Fails("the command was meant to be refused and exited with 0; it says:\n" + ran.Text())
	}
}

func (s *Story) CostIs(name string, counted int) {
	s.t.Helper()

	if got := s.Cost(name); got != counted {
		s.Fails(fmt.Sprintf("%s counted %d since the story began, where %d was meant", name, got, counted))
	}
}

func (s *Story) Parsed(files int) {
	s.t.Helper()

	s.CostIs(ledger.FilesParsedByBridges, files)
}

func (s *Story) CostAtMost(name string, most int) {
	s.t.Helper()

	if got := s.Cost(name); got > most {
		s.Fails(fmt.Sprintf("%s counted %d since the story began, past the %d it may cost", name, got, most))
	}
}

func (s *Story) CostAtLeast(name string, least int) {
	s.t.Helper()

	if got := s.Cost(name); got < least {
		s.Fails(fmt.Sprintf("%s counted %d since the story began, short of the %d it must cost", name, got, least))
	}
}

func (s *Story) CostReaches(name string, least int) {
	s.t.Helper()

	for until := time.Now().Add(hearsWithin); !s.rehearsal && s.Cost(name) < least && time.Now().Before(until); {
		time.Sleep(betweenMoments)
	}

	s.CostAtLeast(name, least)
}

func (s *Story) DropsTheLane(checkout string) Ran {
	s.t.Helper()

	return s.Asks("commandments", "lanes", "--drop="+checkout)
}

func (s *Story) DropsTheTree(checkout string) {
	s.t.Helper()

	s.Says(s.Asks("commandments", "lanes", "--drop-tree="+checkout), "Let go of the tree")
}

func (s *Story) ReadsTheLanes() Ran {
	s.t.Helper()

	return s.Asks("commandments", "lanes")
}

func (s *Story) ReadsTheLanesOnceTheyShow(text string) Ran {
	s.t.Helper()

	lanes := s.ReadsTheLanes()
	for until := time.Now().Add(hearsWithin); !s.rehearsal && !strings.Contains(lanes.Text(), text) && time.Now().Before(until); {
		time.Sleep(betweenMoments)
		lanes, _ = s.journal.Runs(s.project, "commandments", "lanes")
	}

	return lanes
}

func (s *Story) Judged(files int) {
	s.t.Helper()

	s.CostIs(ledger.DetectorFilesJudged, files)
}

func (s *Story) Remembered(files int) {
	s.t.Helper()

	s.CostIs(ledger.DetectorFilesRemembered, files)
}

func (s *Story) SaysTheSameSinsAs(first, again Ran) {
	s.t.Helper()

	was, is := namedAt.FindAllString(first.Text(), -1), namedAt.FindAllString(again.Text(), -1)
	if len(was) == 0 || !slices.Equal(was, is) {
		s.Fails(fmt.Sprintf("the first answer named %v and the next named %v; the next says:\n%s", was, is, again.Text()))
	}
}

// ProgressLine is one line of progress an answer carried, read from the error text the sins alias shows by setting COMMANDMENTS_PLAIN_PROGRESS: the phase counting ("reading" or "detector"), how far it had got and out of how many.
type ProgressLine struct {
	Phase string
	Done  int
	Of    int
}

var progressLine = regexp.MustCompile(`(?m)^(reading|detector) (\d+)/(\d+)$`)

var namedAt = regexp.MustCompile(`([^\s:"'()]+\.[A-Za-z0-9]+):(\d+)`)

func (l ProgressLine) String() string {
	return fmt.Sprintf("%s %d/%d", l.Phase, l.Done, l.Of)
}

func (s *Story) ProgressOf(ran Ran) []ProgressLine {
	var lines []ProgressLine

	for _, found := range progressLine.FindAllStringSubmatch(ran.Text(), -1) {
		done, _ := strconv.Atoi(found[2])
		of, _ := strconv.Atoi(found[3])
		lines = append(lines, ProgressLine{Phase: found[1], Done: done, Of: of})
	}

	return lines
}

func (s *Story) ReadsCounted(ran Ran) int {
	return s.countedIn(ran, "reading")
}

func (s *Story) DetectorsCounted(ran Ran) int {
	return s.countedIn(ran, "detector")
}

func (s *Story) countedIn(ran Ran, phase string) int {
	counted := 0

	for _, line := range s.ProgressOf(ran) {
		if line.Phase == phase {
			counted = line.Of
		}
	}

	return counted
}

func (s *Story) NamesOnlyWholePaths(ran Ran, folder string) {
	s.t.Helper()

	if len(namedAt.FindAllString(ran.Text(), -1)) == 0 {
		s.Fails("the answer names no path:line at all; it says:\n" + ran.Text())

		return
	}

	if lost, missing := pathNotThere(ran.Text(), folder); missing {
		s.Fails(fmt.Sprintf("the answer names %s, which is no file in %s (a path that lost its first slash reads like this); it says:\n%s",
			lost, folder, ran.Text()))
	}
}

func pathNotThere(text, folder string) (string, bool) {
	for _, found := range namedAt.FindAllStringSubmatch(text, -1) {
		path := found[1]
		if !filepath.IsAbs(path) {
			path = filepath.Join(folder, path)
		}

		if _, err := os.Stat(path); err != nil {
			return found[0], true
		}
	}

	return "", false
}
