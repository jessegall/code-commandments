package hooks

import (
	"os/exec"
	"path/filepath"
	"testing"
)

func TestAFileInACheckoutOfItsOwnIsAnsweredFromThatCheckoutWhateverTheLineNames(t *testing.T) {
	project, _ := workerProject(t)
	beside := filepath.Join(filepath.Dir(project), filepath.Base(project)+"-beside")
	if out, err := exec.Command("git", "-C", project, "worktree", "add", "-q", "-b", "beside", beside).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}
	t.Cleanup(func() { _ = exec.Command("git", "-C", project, "worktree", "remove", "--force", beside).Run() })

	file := filepath.Join(beside, "src", "order.py")
	named := Told{Root: project, Given: true}

	if where, _ := named.rootOrDerived(beside, file); where != beside {
		t.Errorf("a file in its own worktree was answered from %s, the checkout the line named, not %s", where, beside)
	}

	if where, said := named.rootOrDerived(project, filepath.Join(project, "src", "order.py")); where != project || said != "the line" {
		t.Errorf("a file in the checkout the line names was answered from %s by %s", where, said)
	}
}
