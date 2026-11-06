package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestAnEditWhoseFileIsNotOnDiskIsRefusedInWords(t *testing.T) {
	root, _ := workerProject(t)

	gone := filepath.Join("src", "cut_away.py")
	if err := os.WriteFile(filepath.Join(root, gone), []byte("class Gone:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	event := NewEvent(map[string]any{
		"hook_event_name": "PostToolUse",
		"tool_name":       "Write",
		"cwd":             root,
		"tool_input":      map[string]any{"file_path": gone},
	}, root)

	if err := os.Remove(filepath.Join(root, gone)); err != nil {
		t.Fatal(err)
	}

	said := SkillReminder{}.Handle(event)

	if said.context == nil {
		t.Fatal("an edit whose file is not on disk was told nothing at all, which is the silence a worker sees when its checkout was cut again")
	}

	context := *said.context

	if !strings.Contains(context, gone) {
		t.Errorf("the refusal does not name the file it could not read: %s", context)
	}

	if !strings.Contains(context, "not on disk") {
		t.Errorf("the refusal does not say why: %s", context)
	}
}
