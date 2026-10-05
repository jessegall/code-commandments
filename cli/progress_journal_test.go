package cli

import (
	"bytes"
	"regexp"
	"strings"
	"testing"
)

// counted is how the journal reads a check's progress: the last `done/total` the output holds.
var counted = regexp.MustCompile(`\b(\d+)\s*/\s*(\d+)\b`)

// TestAJournalCheckReadsTheRunsProgressAsItGoes holds a run a journal check reads to plain counted lines, the
// languages read first and the rules after on one count, and a run nothing reads to silence.
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
	if lines[0] != "reading 1/2" || lines[len(lines)-1] != "judging 6/6" {
		t.Errorf("the check reads %q", written)
	}
	if found := counted.FindAllStringSubmatch(lines[2], -1); len(found) != 1 || found[0][1] != "2" || found[0][2] != "6" {
		t.Errorf("judging starts at %q, not where the languages left the count", lines[2])
	}
	if strings.ContainsAny(written, "\r\033") {
		t.Errorf("a check's lines carry terminal codes: %q", written)
	}
	if quiet := run(""); quiet != "" {
		t.Errorf("a run no check reads writes %q", quiet)
	}
}
