package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
)

func TestAWorktreeCutBesideTheProjectIsStillItsOwnCheckout(t *testing.T) {
	root, _ := workerProject(t)
	beside := filepath.Join(filepath.Dir(root), filepath.Base(root)+"-worker-2")

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/worker-2", beside).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	file := filepath.Join(beside, "src", "order.py")
	if err := os.WriteFile(file, []byte("class Order:\n    def total(self):\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if !git.BelongsTo(beside, git.ProjectRoot(root)) {
		t.Errorf("a worktree beside the project is read as another project: %s", beside)
	}

	if held := checkoutOf(root, file); held != beside {
		t.Errorf("the file's checkout reads as %s, not the worktree it is in", held)
	}

	settings, _ := config.Load(root)
	if judged := judgedFile(root, file, settings); judged == "" {
		t.Errorf("a file in a worktree beside the project is dropped as one this project does not judge: %s", file)
	}
}
