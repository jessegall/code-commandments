package hooks

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

const documentedMethod = "def total(order):\n    \"\"\"Says the total.\"\"\"\n    return 0\n"

func workerEditEvent(agent int, relative string) map[string]any {
	return map[string]any{
		"v": float64(1), "event": editedEvent, "agent": float64(agent),
		"data": map[string]any{"path": relative, "file": map[string]any{"relative": relative}},
	}
}

func TestAnEditIsKeptForTheAgentThatMadeItByItsPathFromItsOwnRoot(t *testing.T) {
	_, data := workerProject(t)

	keepEditOf(workerEditEvent(730, "src/mine.py"))
	keepEditOf(workerEditEvent(730, "src/mine.py"))
	keepEditOf(workerEditEvent(728, "src/other.py"))

	if said := WorkerEditsIn(data).Of(730); !slices.Equal(said, []string{"src/mine.py"}) {
		t.Errorf("agent 730 edited src/mine.py alone, yet its record says %v", said)
	}
}

func handInWithAnEditAndAMergedFile(t *testing.T, named ...string) (merging, string) {
	t.Helper()

	root, data := workerProject(t)

	worktree := stressWorktreeIn(root, 12)
	cutInto(t, root, worktree, "feature/own-edits")

	wroteInto(t, filepath.Join(worktree, "src", "mine.py"), documentedMethod)
	wroteInto(t, filepath.Join(worktree, "src", "merged_in.py"), documentedMethod)

	settings, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	warmFor(t, root, settings)

	keepEditOf(workerEditEvent(730, "src/mine.py"))

	return merging{
		worker: "worker 730", editor: 730, worktree: worktree, base: "main",
		files: named,
	}, data
}

func TestAHandInAnswersOnlyForTheFilesItsOwnEditsNamed(t *testing.T) {
	work, data := handInWithAnEditAndAMergedFile(t)

	found := work.sins()

	if found.got != gotWhole {
		t.Fatalf("the hand-in was not answered from the held tree: %s", found.reason())
	}
	if len(found.marks) != 1 || filepath.Base(found.marks[0].Match.File()) != "mine.py" {
		t.Errorf("the worker edited only mine.py, yet the hand-in reports %v", found.marks)
	}
	if said := WorkerEditsIn(data).Of(730); len(said) != 1 {
		t.Errorf("a refused hand-in lost the worker's edits: %v", said)
	}
}

func TestAHandInJudgesTheFilesItNamesBeforeTheEditsItTracked(t *testing.T) {
	work, _ := handInWithAnEditAndAMergedFile(t, "src/merged_in.py")

	found := work.sins()

	if found.got != gotWhole {
		t.Fatalf("the hand-in was not answered from the held tree: %s", found.reason())
	}
	if len(found.marks) != 1 || filepath.Base(found.marks[0].Match.File()) != "merged_in.py" {
		t.Errorf("the event named only merged_in.py, yet the hand-in reports %v", found.marks)
	}
}

func TestAHandInThatPassesClearsTheWorkersEdits(t *testing.T) {
	work, data := handInWithAnEditAndAMergedFile(t)

	wroteInto(t, filepath.Join(work.worktree, "src", "mine.py"), "LIMIT = 2\n")

	if found := work.sins(); len(found.marks) != 0 {
		t.Fatalf("a clean edit is reported: %v", found.marks)
	}
	if said := WorkerEditsIn(data).Of(730); len(said) != 0 {
		t.Errorf("a hand-in that passed left the edits behind: %v", said)
	}
}
