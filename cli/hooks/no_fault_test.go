package hooks

import (
	"encoding/json"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func TestNothingTheCommitFormsAnswerIsAFault(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	commitIn(t, root, "a sin to find")

	before := git.Head(root)

	wroteInto(t, filepath.Join(root, "src", "more.py"), sinOnLineFive)
	commitIn(t, root, "the work a merge brought in")

	for _, told := range []struct {
		what string
		said string
	}{
		{"a merge that brought in sins", MergeLanded(mergeLine(root, "main", before, git.Head(root)))},
		{"a merge with nothing in its range", MergeLanded(mergeLine(root, "main", git.Head(root), git.Head(root)))},
		{"a merge of a branch nobody knows", MergeLanded(mergeLine(root, "feature/nobody", before, git.Head(root)))},
		{"a merge with no project at all", MergeLanded(map[string]any{"v": 1, "event": landedEvent, "data": map[string]any{}})},
		{"a hand-in whose time ran out", heldAnswer(map[string]any{"event": deliveringEvent, "data": map[string]any{
			"worktree": root, "name": "worker 7",
		}})},
		{"a hand-in with no worktree", heldAnswer(map[string]any{"event": deliveringEvent, "data": map[string]any{
			"name": "worker 7",
		}})},
		{"a hand-in of a worker whose role is not judged", heldAnswer(map[string]any{"event": deliveringEvent, "data": map[string]any{
			"worktree": root, "name": "worker 7", "role": "worker",
		}})},
		{"an unknown key", Answer4Now(map[string]any{"v": 1, "event": "something.nobody.wrote", "agent": 1, "cwd": root})},
	} {
		if told.said == "" {
			continue
		}

		var answer map[string]any
		if err := json.Unmarshal([]byte(told.said), &answer); err != nil {
			t.Errorf("%s answered with something that is not one line of JSON: %q (%v)", told.what, told.said, err)

			continue
		}

		if answer["refuse"] != nil {
			t.Errorf("%s was refused, and a refusal from a commit or listen script is a fault: %s", told.what, told.said)
		}

		if strings.Contains(told.said, "\n") {
			t.Errorf("%s answered with more than one line, and a script prints one: %q", told.what, told.said)
		}
	}
}
