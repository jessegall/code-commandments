package hooks

import (
	"os"
	"time"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/git"
)

const requestedEvent = "suites.coding.workers.merge_request.created"

// requested is a merge the journal is about to make, as its row describes it: the branch it would merge into and
// that branch's tip, the worker's branch and its tip, and the worktree the work is in. The range to judge is what
// the worker's tip adds on top of the target's, read in that worktree.
type requested struct {
	worker  string
	branch  string
	tip     string
	target  string
	onto    string
	folder  string
	project string
	window  time.Duration
}

func requestedOf(line map[string]any) requested {
	fields := eventFields(line)
	row, _ := fields["row"].(map[string]any)
	if row == nil {
		row = fields
	}

	told := toldIn(fields)
	project := told.Project
	if project == "" {
		project = os.Getenv(journalRoot)
	}

	window, _ := windowIn(fields)

	return requested{
		worker:  asText(firstOf(row["worker_id"], row["session_agent_id"])),
		branch:  asText(row["branch"]),
		tip:     asText(row["branch_commit"]),
		target:  asText(row["target_branch"]),
		onto:    asText(row["target_commit"]),
		folder:  asText(row["folder"]),
		project: project,
		window:  window,
	}
}

func MergeRequested(line map[string]any) string {
	asked := requestedOf(line)

	if !mergesJudgedBeforeTheyLand(line) || !asked.readable() {
		return ""
	}

	marks, got := asked.sinsItWouldBringIn()

	switch {
	case got == gotNowhere:
		return asked.allowedBecause("its range could not be read, so nothing of it was judged")
	case len(marks) == 0 && got == gotWhole:
		return ""
	case len(marks) == 0:
		return asked.allowedBecause(string(got))
	}

	refusal := mergeReason(asked.worker, marks, asked.folder)

	if got != gotWhole {
		said := Unjudged("this is not the whole answer", string(got), binary.JudgingChanges(asked.folder))

		return (JournalAnswer{Refuse: &refusal, Whisper: &said}).Line4()
	}

	return (JournalAnswer{Refuse: &refusal}).Line4()
}

func (r requested) readable() bool {
	return r.folder != "" && r.onto != "" && r.tip != "" && r.onto != r.tip
}

func (r requested) allowedBecause(why string) string {
	said := Unjudged("the merge of "+orNothing(r.branch)+" into "+orNothing(r.target)+" was allowed unjudged",
		why, binary.Judging(r.project)+" --branch="+r.target)

	return (JournalAnswer{Whisper: &said}).Line4()
}

func (r requested) sinsItWouldBringIn() ([]SinMark, gotTo) {
	changed := git.ChangedBetween(r.folder, r.onto, r.tip)
	if len(changed) == 0 {
		return nil, gotWhole
	}

	found := make(chan []SinMark, 1)

	go func() {
		marks, _ := marksBrought(r.folder, changed, r.onto, r.tip)
		found <- marks
	}()

	select {
	case marks := <-found:
		return marks, gotWhole
	case <-time.After(r.deadline()):
		return nil, gotPartly
	}
}

func (r requested) deadline() time.Duration {
	if r.window <= 0 {
		return mostAGuardTakes
	}

	return min(r.window-r.window/5, mostAGuardTakes)
}

const mostAGuardTakes = 20 * time.Second

func mergesJudgedBeforeTheyLand(line map[string]any) bool {
	chosen, given := chosenOn(line)
	if !given {
		return false
	}

	said, named := chosen[beforeMergeKey]

	return workersOn(chosen) && named && switchedOn(said)
}

const beforeMergeKey = "judge_before_merge"

func handInsJudged(line map[string]any) bool {
	chosen, given := chosenOn(line)
	if !given {
		return true
	}

	said, named := chosen[handInsKey]

	return workersOn(chosen) && (!named || switchedOn(said))
}

const handInsKey = "judge_hand_ins"
