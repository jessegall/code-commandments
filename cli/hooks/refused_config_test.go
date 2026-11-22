package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestARefusedConfigIsToldOnceAndNotJudgedSilently(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data := t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("JOURNAL_ROOT", root)

	files := map[string]string{
		".commandments/config.json": `{"paths": ["src"], "disable": {"detectors": ["php/NoSuchDetector"]}}`,
		"src/probe.py":              "def probe(value):\n    return value\n",
	}
	for path, text := range files {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	if out, err := exec.Command("git", "-C", root, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	edit := func(toolUse int) string {
		Judge4Into(map[string]any{
			"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 78,
			"data": map[string]any{
				"path": "src/probe.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": toolUse,
				"lines": []any{map[string]any{"line_type": "add", "line": 2, "text": "    return value"}},
			},
		}, data)

		var told []string
		for _, answer := range drained(t, data) {
			told = append(told, asText(answer["whisper"]))
		}

		return strings.Join(told, "\n")
	}

	first := edit(41)
	if !strings.Contains(first, ".commandments/config.json") || !strings.Contains(first, "php/NoSuchDetector") {
		t.Errorf("the first edit is not told the config is refused, and why: %q", first)
	}

	if again := edit(42); strings.Contains(again, "php/NoSuchDetector") {
		t.Errorf("the refusal is told on every edit: %q", again)
	}
}
