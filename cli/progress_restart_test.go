package cli

import (
	"io"
	"slices"
	"testing"
)

func relayed() (*Progress, *[]string) {
	lines := &[]string{}
	progress := NewProgress(ProgressRelay{Writer: io.Discard, Send: func(line string) { *lines = append(*lines, line) }})

	return progress, lines
}

func TestTheDetectorCountIsOnlyTheDetectors(t *testing.T) {
	progress, lines := relayed()

	progress.Plan(8000)
	progress.Restart(3)
	progress.Start()
	progress.Advance("")
	progress.Advance("")
	progress.Advance("")

	if last := (*lines)[len(*lines)-1]; last != "detector 3/3" {
		t.Errorf("the last line is %q, and %v came before it", last, *lines)
	}
}

func TestAReadingCountsItsOwnFiles(t *testing.T) {
	progress, lines := relayed()

	progress.Plan(4)
	reading := progress.Phase("reading")
	reading(2, 4)
	reading(4, 4)

	if !slices.Contains(*lines, "reading 4/4") {
		t.Errorf("the reading never reached its whole count: %v", *lines)
	}
}

func TestDetectorsPlannedAfterNoneAreCountedExactly(t *testing.T) {
	progress, lines := relayed()

	progress.Restart(0)
	progress.Plan(2)
	progress.Start()
	progress.Advance("")
	progress.Advance("")

	if last := (*lines)[len(*lines)-1]; last != "detector 2/2" {
		t.Errorf("the last line is %q", last)
	}
}
