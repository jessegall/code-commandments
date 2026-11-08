package hooks

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

func TestAnEditOfAGeneratedFileIsNotJudgedAndNothingIsSaidAboutIt(t *testing.T) {
	root, _ := workerProject(t)
	settings, _ := config.Load(root)

	generated := filepath.Join(root, "src", "generated.py")
	if err := os.WriteFile(generated, []byte("# @code-commandments-generated\nx = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	event := NewEvent(map[string]any{"hook_event_name": "PostToolUse", "tool_name": "Write", "tool_input": map[string]any{"file_path": generated}}, root)

	if files, lost := edited(event, settings); len(files) != 0 || len(lost) != 0 {
		t.Errorf("an edit of a generated file came to %v to judge and %v said to be lost", files, lost)
	}
}

func TestAnEditOfAFileNoLanguageIsWrittenInIsNotSaidToBeLost(t *testing.T) {
	root, _ := workerProject(t)
	settings, _ := config.Load(root)

	note := filepath.Join(root, "src", "notes.txt")
	if err := os.WriteFile(note, []byte("a note\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	event := NewEvent(map[string]any{"hook_event_name": "PostToolUse", "tool_name": "Write", "tool_input": map[string]any{"file_path": note}}, root)

	if files, lost := edited(event, settings); len(files) != 0 || len(lost) != 0 {
		t.Errorf("an edit of a text file came to %v to judge and %v said to be lost", files, lost)
	}
}
