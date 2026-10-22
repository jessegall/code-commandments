package cli

import (
	"bytes"
	"regexp"
	"slices"
	"strings"
	"testing"
)

// counted is how the journal reads a check's progress: the last `done/total` the output holds.
var counted = regexp.MustCompile(`\b(\d+)\s*/\s*(\d+)\b`)

// TestAJournalCheckReadsTheRunsProgressAsItGoes holds a run a journal check reads to plain lines: one counted step
// for every file the bridges have read and every detector that has run, out of ONE total planned before the run
// began, so the check's bar moves from the start and fills once; a run nothing reads stays silent.
func TestAJournalCheckReadsTheRunsProgressAsItGoes(t *testing.T) {
	run := func(check string) string {
		t.Setenv(JournalReport, check)
		var written bytes.Buffer
		progress := NewProgress(&written)
		progress.Plan(3 + 4)
		reading := progress.Phase("reading")
		for done := range 3 {
			reading(done+1, 3)
		}
		progress.Start()
		for range 4 {
			progress.Advance("")
		}
		progress.Finish()

		return written.String()
	}

	written := run("/tmp/report.json")
	lines := strings.Split(strings.TrimSpace(written), "\n")
	want := []string{"reading 1/7", "reading 2/7", "reading 3/7", "detector 3/7", "detector 4/7", "detector 5/7", "detector 6/7", "detector 7/7"}
	if !slices.Equal(lines, want) {
		t.Errorf("the check reads %q, want %q", lines, want)
	}
	for _, line := range lines {
		if !counted.MatchString(line) {
			t.Errorf("a line the check reads holds no count: %q", line)
		}
	}
	if strings.ContainsAny(written, "\r\033") {
		t.Errorf("a check's lines carry terminal codes: %q", written)
	}
	if quiet := run(""); quiet != "" {
		t.Errorf("a run no check reads writes %q", quiet)
	}
}
