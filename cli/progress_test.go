package cli_test

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// TestCountedLinesAreWrittenWhereAJournalReadsThem holds the counted lines to what reads them: a journal check
// names its report, a journal command asks for them outright, and neither has a terminal. Without either, a run
// whose output is piped says nothing, so a log is never filled with counting.
func TestCountedLinesAreWrittenWhereAJournalReadsThem(t *testing.T) {
	for name, asked := range map[string]map[string]string{
		"a journal check":    {cli.JournalReport: "/tmp/report.json", cli.PlainProgress: ""},
		"a journal command":  {cli.JournalReport: "", cli.PlainProgress: "1"},
		"a run nothing sees": {cli.JournalReport: "", cli.PlainProgress: ""},
	} {
		t.Run(name, func(t *testing.T) {
			for key, value := range asked {
				t.Setenv(key, value)
			}

			want := "reading 3/12\n"
			if name == "a run nothing sees" {
				want = ""
			}

			written := &strings.Builder{}
			progress := cli.NewProgress(written)
			progress.Plan(12)
			progress.Phase("reading")(3, 12)

			if got := written.String(); got != want {
				t.Errorf("%s reads %q, want %q", name, got, want)
			}
		})
	}
}

// TestOneRunFillsOneBarOnce holds the counted lines to one count over one grand total: a run reads one engine's
// files, judges its detectors, then reaches the next engine, and the count carries across every part instead of
// starting again. A reader that takes the last line it saw would otherwise show a bar dropping to zero at each
// new phase.
func TestOneRunFillsOneBarOnce(t *testing.T) {
	t.Setenv(cli.PlainProgress, "1")

	written := &strings.Builder{}
	progress := cli.NewProgress(written)

	progress.Plan(10 + 4)
	reading := progress.Phase("reading")
	reading(0, 10)
	reading(10, 10)

	progress.Start()
	for range 4 {
		progress.Advance("")
	}

	// the next engine: its own files and its own detectors, counted into the same total
	progress.Plan(6 + 2)
	reading(0, 6)
	reading(6, 6)
	progress.Start()
	progress.Advance("")
	progress.Advance("")

	var counts [][2]int
	for _, line := range strings.Split(strings.TrimSpace(written.String()), "\n") {
		var done, total int
		if _, err := fmt.Sscanf(line[strings.Index(line, " ")+1:], "%d/%d", &done, &total); err != nil {
			t.Fatalf("the line %q does not read as a count", line)
		}

		counts = append(counts, [2]int{done, total})
	}

	if len(counts) == 0 {
		t.Fatal("a run watched by a journal wrote no counted line")
	}

	for at, count := range counts {
		if at > 0 && count[0] < counts[at-1][0] {
			t.Errorf("the count went backwards, %d after %d: %v", count[0], counts[at-1][0], counts)
		}

		if count[1] < counts[at-min(at, 1)][1] {
			t.Errorf("the total shrank, %d after %d: %v", count[1], counts[at-1][1], counts)
		}
	}

	last := counts[len(counts)-1]
	if last[0] != 22 || last[1] != 22 {
		t.Errorf("the run ends at %d/%d, want 22/22: ten files and four detectors, then six and two", last[0], last[1])
	}
}
