package cli

import (
	"bytes"
	"regexp"
	"strings"
	"testing"
)

// counted is how the journal reads a check's progress: the last `done/total` the output holds.
var counted = regexp.MustCompile(`\b(\d+)\s*/\s*(\d+)\b`)

// TestAJournalCheckReadsTheRunsProgressAsItGoes holds a run a journal check reads to plain lines: the languages
// named as they are read, with no count of their own, then one counted step for every detector that has run out of
// every detector that will, so the check's bar moves with the rules; a run nothing reads stays silent.
func TestAJournalCheckReadsTheRunsProgressAsItGoes(t *testing.T) {
	run := func(check string) string {
		t.Setenv(journalCheck, check)
		var written bytes.Buffer
		progress := NewProgress(&written)
		progress.Expect(2)
		progress.Step("reading", "PHP")
		progress.Step("reading", "frontend")
		progress.Start(4)
		for range 4 {
			progress.Advance("")
		}
		progress.Finish()

		return written.String()
	}

	written := run("/tmp/report.json")
	lines := strings.Split(strings.TrimSpace(written), "\n")
	if lines[0] != "reading PHP" || lines[1] != "reading frontend" || lines[2] != "detector 0/4" || lines[len(lines)-1] != "detector 4/4" {
		t.Errorf("the check reads %q", written)
	}
	for _, line := range lines[:2] {
		if counted.MatchString(line) {
			t.Errorf("reading a language counts toward the bar: %q", line)
		}
	}
	if strings.ContainsAny(written, "\r\033") {
		t.Errorf("a check's lines carry terminal codes: %q", written)
	}
	if quiet := run(""); quiet != "" {
		t.Errorf("a run no check reads writes %q", quiet)
	}
}
