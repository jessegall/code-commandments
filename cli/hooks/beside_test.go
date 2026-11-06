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

func TestOnePlaceDecidesWhichCheckoutAFileBelongsTo(t *testing.T) {
	root, _ := workerProject(t)
	beside := filepath.Join(filepath.Dir(root), filepath.Base(root)+"-worker-3")

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/worker-3", beside).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	inside := filepath.Join(beside, "src", "made.py")
	if err := os.WriteFile(inside, []byte("class Made:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	elsewhere := t.TempDir()
	if out, err := exec.Command("git", "-C", elsewhere, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("git init: %s", out)
	}

	apart := filepath.Join(elsewhere, "other.py")
	if err := os.WriteFile(apart, []byte("class Other:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, told := range []struct {
		what  string
		file  string
		where string
	}{
		{"a file in the worktree beside the project", inside, beside},
		{"the same file named relative to that worktree", filepath.Join("src", "made.py"), beside},
		{"a file of the project itself", filepath.Join(root, "src", "made.py"), root},
		{"a file in a repository of its own", apart, root},
	} {
		where := checkoutOf(root, told.file)
		if told.file == filepath.Join("src", "made.py") {
			where = checkoutOf(beside, told.file)
		}

		if where != told.where {
			t.Errorf("%s is answered as %s, and it belongs to %s", told.what, where, told.where)
		}
	}
}
