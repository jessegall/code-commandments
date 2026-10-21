package cli_test

import (
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
			progress.Track(3, 12, "reading")

			if got := written.String(); got != want {
				t.Errorf("%s reads %q, want %q", name, got, want)
			}
		})
	}
}
