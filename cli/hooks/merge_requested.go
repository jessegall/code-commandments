package hooks

import (
	"os"

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

	return requested{
		worker:  asText(firstOf(row["worker_id"], row["session_agent_id"])),
		branch:  asText(row["branch"]),
		tip:     asText(row["branch_commit"]),
		target:  asText(row["target_branch"]),
		onto:    asText(row["target_commit"]),
		folder:  asText(row["folder"]),
		project: project,
	}
}

func MergeRequested(line map[string]any) string {
	asked := requestedOf(line)

	if !mergesJudgedBeforeTheyLand(line) || !asked.readable() {
		return ""
	}

	marks := asked.sinsItWouldBringIn()
	if len(marks) == 0 {
		return ""
	}

	refusal := mergeReason(asked.worker, marks, asked.folder)

	return (JournalAnswer{Refuse: &refusal}).Line()
}

func (r requested) readable() bool {
	return r.folder != "" && r.onto != "" && r.tip != "" && r.onto != r.tip
}

func (r requested) sinsItWouldBringIn() []SinMark {
	changed := git.ChangedBetween(r.folder, r.onto, r.tip)
	if len(changed) == 0 {
		return nil
	}

	marks, _ := marksBrought(r.folder, changed, r.onto, r.tip)

	return marks
}

func mergesJudgedBeforeTheyLand(line map[string]any) bool {
	chosen, _ := chosenOn(line)

	return workersChose(chosen, beforeMergeKey)
}

const beforeMergeKey = "judge_before_merge"

func handInsJudged(line map[string]any) bool {
	chosen, _ := chosenOn(line)

	return workersChose(chosen, handInsKey)
}

const handInsKey = "judge_hand_ins"

func orNothing(said string) string {
	if said == "" {
		return "no commit the journal named"
	}

	return said
}
