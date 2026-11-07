package hooks

import (
	"encoding/json"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
)

func TestAHandInIsJudgedFromTheHeldTreeAndReadsNoTree(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	costs := ledger.Of(project)
	readsBefore := costs.Count(ledger.TreeReadsStarted)
	workBefore := engine.Counted()

	held := DeliveryHeld(deliveringOf(worktree, generousWindow, "src/order.py"))

	if parsed := engine.Since(workBefore).Parsed; parsed > 1 {
		t.Errorf("judging a hand-in of one changed file parsed %d files, where the held tree answers for every "+
			"file the worker did not change", parsed)
	}

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hand-in was answered %q: %v", held, err)
	}

	if answer["answer"] != "refuse" {
		t.Errorf("a hand-in carrying a sin was answered %v, where it must go back to the worker", answer["answer"])
	}

	reason := asText(answer["reason"])
	if !strings.Contains(reason, "documented-anywhere") || !strings.Contains(reason, "order.py") {
		t.Errorf("the refusal reads %q, naming neither the sin nor the file the worker wrote it in", reason)
	}

	if strings.Contains(reason, "not judged in full") {
		t.Errorf("with the tree held, the hand-in still says it was not judged in full: %q", reason)
	}

	if reads := costs.Count(ledger.TreeReadsStarted) - readsBefore; reads != 0 {
		t.Errorf("judging a hand-in started %d reads of the whole tree, where the tree already held answers it; "+
			"that read is what made the old hand-in gate take minutes", reads)
	}
}

func TestAHandInWithNoTreeHeldIsNeverReadWholeAndSaysWhatWasNotAsked(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := DeliveryHeld(deliveringOf(root, generousWindow, "src/refund.py"))

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hand-in was answered %q: %v", held, err)
	}

	if answer["answer"] != "refuse" {
		t.Errorf("a sin found by the rules that read one file was answered %v; it is a real sin and the work "+
			"goes back for it", answer["answer"])
	}

	if reason := asText(answer["reason"]); !strings.Contains(reason, "not judged in full") {
		t.Errorf("the refusal does not say the rest was not asked, so the worker reads a partial answer as whole: %q", reason)
	}

	if started := ledger.Of(root).Count(ledger.TreeReadsStarted); started != 0 {
		t.Errorf("with no tree held, the hand-in started %d reads of the whole project to make up for it", started)
	}
}

func TestAHandInIsNotHeldWithTheSettingOff(t *testing.T) {
	root, _, _ := projectWithASin(t)

	t.Setenv(journalSettings, `{"judge_hand_ins": "false"}`)

	if held := DeliveryHeld(deliveringOf(root, generousWindow, "src/refund.py")); held != "" {
		t.Errorf("with hand-ins switched off, the hand-in was answered %q", held)
	}
}
