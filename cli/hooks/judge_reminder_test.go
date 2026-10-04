package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestTheJudgeReminderCountsOnlyTheFilesTheSessionChanged(t *testing.T) {
	root := t.TempDir()
	for _, args := range [][]string{{"init", "-q"}, {"-c", "user.email=a@b", "-c", "user.name=a", "commit", "-q", "--allow-empty", "-m", "init"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", args, out)
		}
	}

	os.MkdirAll(filepath.Join(root, "src"), 0o777)
	others := filepath.Join(root, "src", "Others.php")
	os.WriteFile(others, []byte("<?php\n"), 0o666)

	stop := func(session string) Response {
		return JudgeReminder{}.Handle(NewEvent(map[string]any{"hook_event_name": "Stop", "session_id": session}, root))
	}

	if !stop("reader").IsSilent() {
		t.Fatal("a session that changed nothing is not reminded of another session's files")
	}

	mine := filepath.Join(root, "src", "Mine.php")
	os.WriteFile(mine, []byte("<?php\n"), 0o666)
	JudgeReminder{}.Handle(NewEvent(map[string]any{"hook_event_name": "PostToolUse", "session_id": "writer", "tool_name": "Write", "tool_input": map[string]any{"file_path": mine}}, root))

	shell := map[string]any{"session_id": "writer", "tool_name": "Bash", "tool_input": map[string]any{"command": "sed -i x src/Shell.php"}}
	JudgeReminder{}.Handle(NewEvent(with(shell, "PreToolUse"), root))
	os.WriteFile(filepath.Join(root, "src", "Shell.php"), []byte("<?php\n"), 0o666)
	JudgeReminder{}.Handle(NewEvent(with(shell, "PostToolUse"), root))

	reminded := stop("writer")
	if reminded.IsSilent() || !strings.Contains(reminded.JSON("Stop"), "changed 2 judged files") {
		t.Fatalf("the writer is reminded of its own two files, one written and one changed by the shell: %+v", reminded)
	}
}

func with(payload map[string]any, event string) map[string]any {
	copied := map[string]any{"hook_event_name": event}
	for key, value := range payload {
		copied[key] = value
	}

	return copied
}
