package hooks

import (
	"os"
	"path/filepath"
	"strconv"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestAQueuedRefusalSaysItsWordsOnce(t *testing.T) {
	reason := "before you wrap up"

	told := told(JournalAnswer{Refuse: &reason, Whisper: &reason})

	if told.Whisper == nil || *told.Whisper != reason {
		t.Errorf("the queued words are %v, and the reason was given once", told.Whisper)
	}

	if shape := (JournalAnswer{Refuse: &reason, Whisper: &reason}).Shape4(); shape["reason"] != reason {
		t.Errorf("the refusal says %q, and the reason was given once", shape["reason"])
	}
}

func TestEveryFileOfABatchedEditIsCounted(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	first, second := filepath.Join(root, "a.py"), filepath.Join(root, "b.py")
	for _, file := range []string{first, second} {
		if err := os.WriteFile(file, nil, 0o644); err != nil {
			t.Fatal(err)
		}
	}

	event := NewEvent(map[string]any{
		"hook_event_name": "PostToolUse", "tool_name": "Write", "session_id": "s1",
		"tool_input": map[string]any{"file_path": first},
		batchedKey:   []any{first, second},
	}, root)

	JudgeReminder{}.Handle(event)

	if counted := len(AuthoredIn(event.Workspace()).Among(map[string]bool{first: true, second: true})); counted != 2 {
		t.Errorf("a batch of 2 files was edited and %d are counted", counted)
	}
}

func TestEditsJudgedAtTheSameMomentAreAllCounted(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	authored := AuthoredIn(workspace.At(root, "s1"))
	changed := map[string]bool{}

	var edits sync.WaitGroup

	for n := range 24 {
		file := filepath.Join(root, strconv.Itoa(n)+".py")
		if err := os.WriteFile(file, nil, 0o644); err != nil {
			t.Fatal(err)
		}

		changed[file] = true

		edits.Add(1)

		go func() {
			defer edits.Done()
			authored.Wrote(root, file)
		}()
	}

	edits.Wait()

	if counted := len(authored.Among(changed)); counted != len(changed) {
		t.Errorf("%d files were edited and %d are counted", len(changed), counted)
	}
}
