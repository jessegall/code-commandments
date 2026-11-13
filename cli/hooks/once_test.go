package hooks

import (
	"path/filepath"
	"strings"
	"testing"
)

func TestOneEditArrivingByBothRoutesIsAnsweredOnce(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	heard := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd":  root,
		"data": map[string]any{"cwd": root, "kind": "create", "path": under},
	}

	routed := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd":  root,
		"data": map[string]any{"cwd": root, "kind": "create", "path": under, "route": "service"},
	}

	Judge4Into(heard, data)

	first := drained(t, data)
	if len(first) == 0 {
		t.Fatal("the edit was answered with nothing at all by the route it was heard on")
	}

	Judge4Into(routed, data)

	second := drained(t, data)

	for _, answer := range second {
		if strings.Contains(asText(answer["whisper"]), under) {
			t.Fatalf("the same edit named %s a second time, so the agent is marked twice for one edit:\n%v", under, answer)
		}

		if cards, held := answer["raises"].([]any); held && len(cards) > 0 {
			t.Fatalf("the same edit raised %d card(s) a second time:\n%v", len(cards), answer)
		}
	}
}
