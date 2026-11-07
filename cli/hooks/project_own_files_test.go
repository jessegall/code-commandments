package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func TestAWorkerIsNotJudgedOnAFileOnlyTheProjectHas(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "development", "worker-9")

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/worker-9", worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	onlyHere := filepath.Join(root, "src", "only_in_the_project.py")
	if err := os.WriteFile(onlyHere, []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	added := git.AddedVsHead(root)
	if len(added) == 0 {
		t.Fatal("git says the project holds nothing its commit does not, so this test would prove nothing")
	}

	var absolute []string
	for file := range added {
		absolute = append(absolute, file)
	}

	if !slices.ContainsFunc(absolute, func(file string) bool { return filepath.Base(file) == "only_in_the_project.py" }) {
		t.Fatalf("the file only the project has is not among what git says the commit lacks: %v", absolute)
	}

	if _, err := os.Stat(filepath.Join(worktree, "src", "only_in_the_project.py")); err == nil {
		t.Fatal("the worktree holds the file too, so there is nothing for a view to leave out")
	}
}

func TestAFileTheProjectOnlyChangedStaysInAWorkersView(t *testing.T) {
	root, _ := workerProject(t)

	tracked := filepath.Join(root, "src", "order.py")
	if _, err := os.Stat(tracked); err != nil {
		t.Skipf("the project fixture has no committed file to change: %v", err)
	}

	if err := os.WriteFile(tracked, []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	for file := range git.AddedVsHead(root) {
		if filepath.Base(file) == "order.py" {
			t.Error("a committed file the project merely changed counts as one the commit lacks, so a worker's " +
				"view would lose a file it really does hold")
		}
	}
}
