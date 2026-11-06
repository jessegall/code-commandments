package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

func TestAnEditInAWorktreeBesideTheProjectIsJudged(t *testing.T) {
	root, data := workerProject(t)
	beside := filepath.Join(filepath.Dir(root), filepath.Base(root)+"-worker-2")

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/worker-2", beside).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	under := filepath.Join("src", "made.py")
	file := filepath.Join(beside, under)
	if err := os.WriteFile(file, []byte("class Made:\n    def total(self):\n        \"\"\"Says it.\"\"\"\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	linked := filepath.Join(t.TempDir(), "project")
	if err := os.Symlink(root, linked); err != nil {
		t.Skipf("this filesystem has no links: %v", err)
	}
	t.Setenv("JOURNAL_ROOT", linked)

	line := map[string]any{
		"v": 1, "event": "file_edited", "session": "s7", "environment": "main", "agent": 7,
		"cwd":  beside,
		"data": map[string]any{"cwd": beside, "kind": "create", "path": under, "added": 4, "removed": 0, "tool_use": 2},
	}

	moment, known := Moment4Of(line)
	if !known {
		t.Fatal("the journal's own edit event is not a moment at all")
	}

	given := Stamped(moment)
	held := MomentOf(given)

	if held.File == nil || *held.File != file {
		t.Fatalf("the moment names the file as %v, not the one in the worktree beside the project", held.File)
	}

	settings, _ := config.Load(root)
	if judged := judgedFile(held.Cwd, *held.File, settings); judged == "" {
		t.Fatalf("the judging drops the file as one this project does not judge, with the moment's own cwd %s", held.Cwd)
	}

	Judge4Into(line, data)

	queued := drained(t, data)
	if len(queued) == 0 {
		t.Fatal("an edit in a worktree beside the project was told nothing at all, which is the silence the harness sees")
	}
}
