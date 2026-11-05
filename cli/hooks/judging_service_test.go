package hooks

import (
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
)

// TestAJudgingAskedForOnTheSocketIsAnsweredFromTheTreeTheServiceHolds holds the command's half of the warm tree.
// A terminal run is handed no socket and no data folder, only the folder it was started in, so the socket is
// named from the project's path as a language bridge's is: the run computes the same name, asks, and the service
// judges against the tree it already holds instead of the run reading 1518 or 5286 files for itself.
//
// The sin it names is one only a cross-file read can find, so the answer also says the judging was done against
// the program and not against one file.
func TestAJudgingAskedForOnTheSocketIsAnsweredFromTheTreeTheServiceHolds(t *testing.T) {
	root, _ := migrationProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase)
	t.Cleanup(func() { kept.Drop(project, branch) })

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	answer, asked := warm.Asked(root, warm.Request{
		Judge: []string{root, "--no-checklist", "--sin=migration-by-its-parents"},
		In:    root,
	})
	if !asked {
		t.Fatal("the run found no service on the socket named from the project")
	}

	// The console names the rule and the place, which is what a reader of a judging sees.
	for _, said := range []string{"MigrationByItsParentsDetector", "m004_drop_fades.py:4"} {
		if !strings.Contains(answer.Out, said) {
			t.Errorf("the answer does not name %s:\n%s%s", said, answer.Out, answer.Err)
		}
	}

	if answer.Code != 1 {
		t.Errorf("the answer exits %d, want 1 for a run that found a sin", answer.Code)
	}
}

// TestARunFindsNoServiceWhereNoneListens holds the fallback: nothing listening is no answer, and the run reads
// the tree itself as every run did before one was held.
func TestARunFindsNoServiceWhereNoneListens(t *testing.T) {
	root, _ := migrationProject(t)

	if _, asked := warm.Asked(root, warm.Request{Judge: []string{root}, In: root}); asked {
		t.Error("a run was answered by a service nobody started")
	}
}

// TestTheAnswerSaysHowLongThePluginTookOverTheEvent holds the two durations the journal shows on the sins card:
// what the judging itself took, and how long the plugin held the event altogether. They differ by the wait an
// event spends for its turn, since one event is judged at a time, and a reader given only the total cannot tell
// a slow judging from a queued one. Neither counts the time before the event, which is the journal's own.
func TestTheAnswerSaysHowLongThePluginTookOverTheEvent(t *testing.T) {
	root, data := migrationProject(t)

	// An answer is what carries the duration, so the base is held and the sin found: with nothing to say the
	// plugin answers nothing at all, which is the point of saying it only beside an answer.
	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase)
	t.Cleanup(func() { kept.Drop(project, branch) })

	Judge4Into(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 78,
		"data": map[string]any{
			"path": "shop/migrations/m004_drop_fades.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 4, "text": "class DropFadesMigration(RebuildMigration):"},
			},
		},
	}, data)

	queued := drained(t, data)
	if len(queued) != 1 {
		t.Fatalf("the judge left %d answer(s)", len(queued))
	}

	took, said := queued[0]["judged_ms"].(float64)
	if !said || took < 1 {
		t.Errorf("the answer says judged_ms %v, said %v; it should carry the milliseconds the judging took", took, said)
	}

	// The total is the judging plus whatever the event waited for its turn, so it is never the smaller of the
	// two: a reader comparing them is being told that wait.
	whole, told := queued[0]["judged_total_ms"].(float64)
	if !told || whole < took {
		t.Errorf("the answer says judged_total_ms %v against a judging of %v, told %v", whole, took, told)
	}

	if whole > float64(10*time.Minute/time.Millisecond) {
		t.Errorf("the answer says it held the event %v ms, which is no duration of one", whole)
	}

	// The reading's share is a part of the judging, never more than it: it is how much of that work was a
	// bridge parsing files rather than a rule reading the tree.
	read, shared := queued[0]["read_ms"].(float64)
	if shared && read > took {
		t.Errorf("the answer says read_ms %v of a judging of %v", read, took)
	}
}

// TestAJudgingTheBaseCannotCoverIsDeclined holds what the service must not do: read a tree inside the one
// process that answers every hook of the session. A worker ran a judging of its own worktree, whose files no base
// holds, and two later edits of its own waited two minutes behind that read. Declined, the run reads them in its
// own process, where it blocks nobody.
func TestAJudgingTheBaseCannotCoverIsDeclined(t *testing.T) {
	root, _ := migrationProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)
	kept.Drop(project, branch)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	if answer, asked := warm.Asked(root, warm.Request{Judge: []string{root, "--no-checklist"}, In: root}); asked {
		t.Errorf("with no base held the service judged it anyway: exit %d\n%s", answer.Code, answer.Out)
	}
}
