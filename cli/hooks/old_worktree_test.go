package hooks

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
)

func TestAWorkerCutLongAgoIsStillJudgedFromTheTree(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	for at := range 400 {
		path := filepath.Join(root, "src", fmt.Sprintf("later_%03d.py", at))
		if err := os.WriteFile(path, []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	for _, line := range [][]string{{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"}, {"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the branch moves on after the cut"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	nowServing(t)

	answer := judgedFor(warm.Request{Judge: []string{"--changes", "--no-checklist"}, In: worktree}, root, func(string) {})

	if answer.Declined {
		t.Fatalf("a worker cut before its branch moved on by 400 files was declined: %s", answer.Because)
	}

	if strings.Contains(answer.Err, "smaller") {
		t.Errorf("a worker cut before its branch moved on by 400 files was answered smaller, though it changed one "+
			"file and the tree of its branch is held:\n%s", answer.Err)
	}
}
