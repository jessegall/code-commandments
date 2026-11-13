package cli_test

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

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

func TestAPlainProgressLineReadsBackAsTheBarItCounts(t *testing.T) {
	t.Setenv(cli.PlainProgress, "1")

	for _, phase := range []string{"judging", "reading"} {
		written := &strings.Builder{}
		progress := cli.NewProgress(written)
		progress.At(phase, 3, 12)

		read, done, total, isProgress := cli.PlainProgressOf(written.String())
		if !isProgress || read != phase || done != 3 || total != 12 {
			t.Errorf("%q reads back as %q %d/%d (%v), want %s 3/12", written.String(), read, done, total, isProgress, phase)
		}
	}

	for _, line := range []string{"1 sin in src/order.py", "waiting: 2 ahead of you", "reading 3/12 files", "judging 3/x"} {
		if _, _, _, isProgress := cli.PlainProgressOf(line); isProgress {
			t.Errorf("%q reads as a progress line", line)
		}
	}
}

func TestARelayedRunWritesItsProgressAsLinesAndKeepsItsColour(t *testing.T) {
	t.Setenv(cli.PlainProgress, "")
	t.Setenv(cli.JournalReport, "")
	t.Setenv("NO_COLOR", "")
	t.Setenv(cli.Relayed, "1")

	written := &strings.Builder{}
	cli.NewProgress(written).At("reading", 3, 12)
	if written.String() != "reading 3/12\n" {
		t.Errorf("a relayed run's progress reads %q, not a line its terminal judge can redraw", written.String())
	}

	coloured := &strings.Builder{}
	fmt.Fprint(cli.Coloured(coloured), "\033[1msin\033[0m")
	if coloured.String() != "\033[1msin\033[0m" {
		t.Errorf("a relayed run writes %q, its colour stripped", coloured.String())
	}

	if cli.Watched() {
		t.Error("a relayed run counts as watched by the journal, so it prints the journal's report instead of the terminal's")
	}
}
