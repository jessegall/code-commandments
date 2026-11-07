package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func TestAHookIsAnsweredAtOnceEvenWithNoTreeToAnswerFrom(t *testing.T) {
	root := t.TempDir()

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	nowServing(t)

	if _, _, why := answering(root, root); why == "" {
		t.Fatal("a tree is already held, so this test would not prove that an answer comes without one")
	}

	line := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd": root,
		"data": map[string]any{
			"cwd": root, "kind": "create", "path": filepath.Join("src", "order.py"),
			"checkout": map[string]any{"project_root": root, "branch": "main"},
		},
	}

	started := time.Now()
	said := Answer4Now(line)
	took := time.Since(started)

	if took > 2*time.Second {
		t.Errorf("the journal waited %v for our answer to an edit; a hook is answered at once and the judging "+
			"happens behind it, whatever the tree is doing", took)
	}

	t.Logf("an edit with no tree held was answered in %v", took)

	if _, err := os.Stat(filepath.Join(root, "src", "order.py")); err != nil {
		t.Fatalf("the file the test wrote is not there: %v", err)
	}

	if strings.Contains(said, string(filesNotOnDisk)) {
		t.Errorf("the answer says what changed is not on disk, about a file that IS on disk at %s:\n%s",
			filepath.Join(root, "src", "order.py"), said)
	}
}
