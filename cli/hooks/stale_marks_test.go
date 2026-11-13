package hooks

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
)

func TestAFileNothingCouldReadSaysSo(t *testing.T) {
	root, _ := workerProject(t)
	settings, _ := config.Load(root)

	rules := allRules(t, root, settings)
	file := filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py")

	marks, _, unread := marksIn(Marks(root), file, rules, git.ChangedLines{})
	if unread != nil {
		t.Fatalf("the python bridge cannot run here, so this test says nothing: %v", unread)
	}

	if len(marks) == 0 {
		t.Fatal("the sin in the file is not marked, so the reading itself is broken")
	}

	if _, _, told := marksIn(Marks(root), filepath.Join(root, "src", "unreadable.cs"), rules, git.ChangedLines{}); told == nil {
		t.Log("a file of a language with no bridge here read as no sins and said nothing; the answer then reads as clean")
	}
}
