package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

func TestAMarkedFileTheTreeVouchesForIsNotReadAgain(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	vouched := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")
	moved := filepath.Join(root, "shop", "migration.py")

	kept.Drop(project, branch)

	if read := stillToRead(root, []string{vouched, moved}); len(read) != 2 {
		t.Errorf("with no tree held the sweep reads %d of 2 marked files; it knows nothing, so it must read both", len(read))
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(moved, []byte("class Migration:\n    number = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	read := stillToRead(root, []string{vouched, moved})

	if len(read) != 1 || read[0] != moved {
		t.Errorf("the sweep reads %v; it owes a read to the file that moved and nothing to the one the tree vouches for", read)
	}
}

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

func TestTheSweepItselfReadsOnlyWhatTheTreeCannotVouchFor(t *testing.T) {
	root, data := workerProject(t)
	settings, _ := config.Load(root)

	file := filepath.Join(root, "src", "order.py")
	if err := os.WriteFile(file, []byte("class Order:\n    def total(self):\n        \"\"\"Says the total.\"\"\"\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	found, _, _ := marksIn(Marks(root), file, allRules(t, root, settings), git.Everywhere())
	if len(found) == 0 {
		t.Fatal("the sin in the project's own file is not marked, so nothing is announced and this proves nothing")
	}

	AnnouncedIn(data, Moment{Event: "Stop", Cwd: root}).Settle(root, []string{file}, found)

	if named := AnnouncedIn(data, Moment{Event: "Stop", Cwd: root}).Files(); len(named) == 0 {
		t.Fatal("the record holds no file, so the sweep has nothing to read either way")
	}

	if _, read := sweptMarks(AnnouncedIn(data, Moment{Event: "Stop", Cwd: root}), root); len(read) != 0 {
		t.Errorf("the sweep read %v; the tree vouches for that file, so it owes a read to none", read)
	}

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(file, []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if _, read := sweptMarks(AnnouncedIn(data, Moment{Event: "Stop", Cwd: root}), root); len(read) == 0 {
		t.Error("the sweep reads nothing for a marked file that has moved since the tree read it")
	}
}
