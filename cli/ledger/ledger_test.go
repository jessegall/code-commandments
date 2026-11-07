package ledger

import (
	"strings"
	"sync"
	"testing"
)

func TestTheLedgerAddsUpWhatTheServiceCost(t *testing.T) {
	costs := Of(t.TempDir())

	costs.Counts(TreeReadsStarted, 1, "/project")
	costs.Counts(TreeReadsFinished, 1, "/project")
	costs.Counts(FilesReadIntoTrees, 120, "/project")
	costs.Counts(FilesReadIntoTrees, 30, "/project")

	if read := costs.Count(FilesReadIntoTrees); read != 150 {
		t.Errorf("the ledger holds %d files read into trees, where two reads of 120 and 30 make 150", read)
	}

	if started := costs.Count(TreeReadsStarted); started != 1 {
		t.Errorf("the ledger holds %d tree reads started, where one began", started)
	}

	if failed := costs.Count(TreeReadsFailed); failed != 0 {
		t.Errorf("the ledger holds %d failed reads, where none failed", failed)
	}

	if events := costs.Events(); len(events) != 4 || !strings.Contains(events[2], FilesReadIntoTrees) {
		t.Errorf("the ledger kept %v, where four events in order were written", events)
	}
}

func TestTheLedgerLosesNoCountWhenJudgingsArriveTogether(t *testing.T) {
	costs := Of(t.TempDir())

	var together sync.WaitGroup
	for range 40 {
		together.Go(func() { costs.Counts(JudgingsFromTheTree, 1, "/worktree") })
	}
	together.Wait()

	if answered := costs.Count(JudgingsFromTheTree); answered != 40 {
		t.Errorf("forty judgings answered at once were counted as %d, so a story reading cost would be told less "+
			"than the service did", answered)
	}
}

func TestTheLedgerKeepsOnlyTheNewestEvents(t *testing.T) {
	costs := Of(t.TempDir())

	for range eventsKept + 25 {
		costs.Counts(JudgingsDeclined, 1, "/worktree")
	}

	if kept := len(costs.Events()); kept != eventsKept {
		t.Errorf("the ledger keeps %d events, where it must keep the newest %d and no more", kept, eventsKept)
	}

	if declined := costs.Count(JudgingsDeclined); declined != eventsKept+25 {
		t.Errorf("the count is %d after %d declines; trimming the events must not trim the count", declined,
			eventsKept+25)
	}
}
