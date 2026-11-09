//go:build story

package progress

import (
	"fmt"
	"regexp"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/story"
)

var (
	files = []story.PlainFile{
		{Path: "src/refund.py", Text: story.ElseAfterRaise.Text},
		{Path: "src/total.py", Text: story.Clean.Text},
	}

	colours = regexp.MustCompile(`\x1b\[[0-9;]*m`)
)

func linesOf(s *story.Story, ran story.Ran, phase string) []story.ProgressLine {
	var of []story.ProgressLine
	for _, line := range s.ProgressOf(ran) {
		if line.Phase == phase {
			of = append(of, line)
		}
	}

	return of
}

func listedUnder(listing, skill string) int {
	listed, under := 0, false

	for _, line := range strings.Split(colours.ReplaceAllString(listing, ""), "\n") {
		switch {
		case strings.HasPrefix(line, "  "):
			if under {
				listed++
			}
		case strings.TrimSpace(line) != "":
			under = strings.TrimSpace(line) == skill
		}
	}

	return listed
}

func TestAColdReadCountsTheFilesThenTheDetectors(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a sinful file and a clean one", files...)

	ran := project.JudgesWith([]string{"COMMANDMENTS_PLAIN_PROGRESS=1"}, "src", "--no-checklist")

	reading, detecting := linesOf(s, ran, "reading"), linesOf(s, ran, "detector")
	if len(reading) == 0 || len(detecting) == 0 {
		s.Fails(fmt.Sprintf("a cold judging shows both counts, and this one shows %d reading lines and %d detector lines:\n%s",
			len(reading), len(detecting), ran.Err))

		return
	}

	if last := reading[len(reading)-1]; last.Done != last.Of || last.Of != len(files) {
		s.Fails(fmt.Sprintf("reading ends at %s, where it ends at the %d files read:\n%s", last, len(files), ran.Err))
	}

	if first := detecting[0]; first.Done > 1 {
		s.Fails(fmt.Sprintf("the detectors begin at %s, where they count from the start, apart from the reading:\n%s", first, ran.Err))
	}

	for _, line := range detecting {
		if line.Done > line.Of {
			s.Fails(fmt.Sprintf("a detector line counts %s, past the number of detectors:\n%s", line, ran.Err))
		}
	}

	if last := detecting[len(detecting)-1]; last.Done != last.Of {
		s.Fails(fmt.Sprintf("the detectors end at %s:\n%s", last, ran.Err))
	}

	if all := s.ProgressOf(ran); all[len(reading)-1].Phase != "reading" || all[len(reading)].Phase != "detector" {
		s.Fails("the reading lines do not come before the detector lines:\n" + ran.Err)
	}
}

func TestAHeldTreeHasNoReadingLines(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(story.ElseAfterRaise, "held.py")

	ran := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	if reading := linesOf(s, ran, "reading"); len(reading) != 0 {
		s.Fails("a held tree reads nothing and shows reading lines:\n" + ran.Err)
	}

	if len(linesOf(s, ran, "detector")) == 0 {
		s.Fails("the answer shows no detector line:\n" + ran.Err)
	}
}

func TestANarrowedJudgingCountsOneDetector(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(story.ElseAfterRaise, "narrow.py")

	ran := s.AsksIn(s.TestCheckout(), "sins", "--changes", "--sin=redundant-python-else")

	detecting := linesOf(s, ran, "detector")
	if len(detecting) == 0 {
		s.Fails("the answer shows no detector line:\n" + ran.Text())
	}

	for _, line := range detecting {
		if line.Of != 1 {
			s.Fails(fmt.Sprintf("one sin was asked for and a line counts %s:\n%s", line, ran.Err))
		}
	}
}

func TestTheDetectorCountIsTheNumberTheListNames(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(story.ElseAfterRaise, "listed.py")

	listing := s.AsksIn(s.TestCheckout(), "commandments", "judge", "--list")
	listed := listedUnder(listing.Text(), "python/flow")
	if listed == 0 {
		s.Fails("the list names no detector under python/flow:\n" + listing.Text())
	}

	ran := s.AsksIn(s.TestCheckout(), "sins", "--changes", "--skill=python/flow")

	if counted := s.DetectorsCounted(ran); counted != listed {
		s.Fails(fmt.Sprintf("the list names %d detectors under python/flow and the judging counts %d:\n%s", listed, counted, ran.Err))
	}
}

func TestWithoutTheAliasSwitchNoProgressLinesShow(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(story.ElseAfterRaise, "plain.py")

	ran := s.AsksIn(s.TestCheckout(), "commandments", "judge", "--changes")

	if lines := s.ProgressOf(ran); len(lines) != 0 {
		s.Fails("an answer of the commandments alias shows progress lines:\n" + ran.Err)
	}
}
