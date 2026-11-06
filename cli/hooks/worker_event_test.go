package hooks

import (
	"path/filepath"
	"strings"
	"testing"
)

func TestAWorkersEventIsAnswered(t *testing.T) {
	root, _ := workerProject(t)
	worker := filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py")

	line := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 545,
		"data": map[string]any{
			"path": ".journal/worktrees/worker-1/src/order.py", "kind": "edit", "added": 2, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 2, "text": "    def total(self):"},
				map[string]any{"line_type": "add", "line": 3, "text": `        """Says the total."""`},
			},
		},
	}

	moment, four := Moment4Of(line)
	if !four {
		t.Fatal("the event is not a version 4 moment")
	}

	given := Stamped(moment)
	event := NewEvent(MomentOf(given).HookPayload(), root)

	if changed := event.ChangedLines(worker); !changed.Covers(2) {
		t.Fatalf("the lines the edit wrote are %+v, which do not cover the sin's line", changed)
	}

	response := SkillReminder{}.Handle(event)

	if len(response.Judged) == 0 {
		t.Fatal("the check read no file at all, so a worker's edit is answered by nothing")
	}

	if len(response.Activity) == 0 {
		t.Fatalf("the check read %v and found no sin in the worker's file", response.Judged)
	}

	if named := response.Activity[0].Found(); !strings.Contains(named, "documented-anywhere") {
		t.Errorf("the mark names %q", named)
	}
}
