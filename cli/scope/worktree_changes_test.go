package scope

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func gitIn(t *testing.T, folder string, line ...string) {
	t.Helper()

	if out, err := exec.Command("git", append([]string{"-C", folder}, line...)...).CombinedOutput(); err != nil {
		t.Fatalf("git %v: %s", line, out)
	}
}

func writtenIn(t *testing.T, path, content string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
		t.Fatal(err)
	}
}

func projectWithAWorker(t *testing.T) (project, worktree string) {
	t.Helper()

	project = t.TempDir()

	gitIn(t, project, "init", "-q", "-b", "main")
	gitIn(t, project, "config", "user.email", "t@t")
	gitIn(t, project, "config", "user.name", "t")

	writtenIn(t, filepath.Join(project, "src", "order.py"), "x = 1\n")
	gitIn(t, project, "add", "-A")
	gitIn(t, project, "commit", "-qm", "first")

	worktree = filepath.Join(project, ".journal", "worktrees", "main", "worker-1")
	gitIn(t, project, "worktree", "add", "-q", "-b", "journal/main/worker-1", worktree)

	return project, worktree
}

func TestAWorkersNewFileIsJudgedAfterTheJournalCommittedIt(t *testing.T) {
	_, worktree := projectWithAWorker(t)

	brought := filepath.Join(worktree, "src", "brought.py")
	writtenIn(t, brought, "def f(x):\n    if x:\n        return 1\n    else:\n        return 2\n")
	gitIn(t, worktree, "add", "-A")
	gitIn(t, worktree, "commit", "-qm", "the journal commits a worker's work before it is sent anything")

	held, err := FromArgs([]string{"judge", "--changes"}, worktree, workspace.At(worktree, ""), nil)
	if err != nil {
		t.Fatal(err)
	}

	real, _ := filepath.EvalSymlinks(brought)
	if !held.Includes(real) {
		t.Fatalf("a file the worker wrote is out of scope once the journal committed it, so its judge says "+
			"'Nothing to judge' over three files full of sins; scope holds %v", held.Files())
	}

	if !held.Covers(real, 4) {
		t.Error("the worker's file is in scope but its lines are not, so every sin in it is filtered from the report")
	}

	if held.Includes(filepath.Join(worktree, "src", "order.py")) {
		t.Error("a file the worker never touched is in scope")
	}
}

func TestAWorkersUncommittedFileIsJudgedToo(t *testing.T) {
	_, worktree := projectWithAWorker(t)

	brought := filepath.Join(worktree, "src", "untracked.py")
	writtenIn(t, brought, "y = 2\n")

	held, err := FromArgs([]string{"judge", "--changes"}, worktree, workspace.At(worktree, ""), nil)
	if err != nil {
		t.Fatal(err)
	}

	real, _ := filepath.EvalSymlinks(brought)
	if !held.Includes(real) {
		t.Errorf("a new untracked file in a worker's worktree is out of scope: %v", held.Files())
	}
}

func TestTheMainCheckoutStillJudgesOnlyWhatIsUncommitted(t *testing.T) {
	project, _ := projectWithAWorker(t)

	writtenIn(t, filepath.Join(project, "src", "landed.py"), "z = 3\n")
	gitIn(t, project, "add", "-A")
	gitIn(t, project, "commit", "-qm", "committed in the main checkout")

	held, err := FromArgs([]string{"judge", "--changes"}, project, workspace.At(project, ""), nil)
	if err != nil {
		t.Fatal(err)
	}

	if len(held.Files()) != 0 {
		t.Errorf("the main checkout judges committed work as a change: %v", held.Files())
	}
}
