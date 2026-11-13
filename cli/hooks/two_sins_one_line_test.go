package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestTwoSinsOnOneLineAreBothTold(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data := t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("JOURNAL_ROOT", root)

	files := map[string]string{
		".commandments/config.json": `{"paths": ["src"], "detectors": ["DocstringInMethodDetector", "PoeticMethodNameDetector"]}`,
		".commandments/custom/DocstringInMethodDetector.json": `{"engine": "python",
 "sin": {"name": "docstring-in-method", "description": "a docstring inside a method", "skill": "python/documentation"},
 "find": {"select": "function", "where": [{"documented": true}]}}`,
		".commandments/custom/PoeticMethodNameDetector.json": `{"engine": "python",
 "sin": {"name": "poetic-method-name", "description": "a method named by a word that does not say what it does", "skill": "python/documentation"},
 "find": {"select": "function", "where": [{"name": "muse"}]}}`,
		"src/probe.py": "class Whisper:\n    def muse(self, value):\n        \"\"\"Says the value.\"\"\"\n        return value\n",
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

	Judge4Into(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 78,
		"data": map[string]any{
			"path": "src/probe.py", "kind": "edit", "added": 4, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 1, "text": "class Whisper:"},
				map[string]any{"line_type": "add", "line": 2, "text": "    def muse(self, value):"},
				map[string]any{"line_type": "add", "line": 3, "text": `        """Says the value."""`},
				map[string]any{"line_type": "add", "line": 4, "text": "        return value"},
			},
		},
	}, data)

	queued := drained(t, data)
	if len(queued) != 1 {
		t.Fatalf("the judge left %d answer(s): %v", len(queued), queued)
	}

	told := asText(queued[0]["whisper"])

	for _, sin := range []string{"docstring-in-method", "poetic-method-name"} {
		if !strings.Contains(told, sin) {
			t.Errorf("the mark does not name %s", sin)
		}
	}
}
