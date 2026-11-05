package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// TestEveryEngineARuleJudgesIsAskedAboutAnEditedFile holds the live check to every engine a rule names. A project
// may write one rule for two languages, and the mark asked only the engine written FIRST: a consumer's rule
// naming go and python was never asked about a python file, so a rule they wrote was silently off in the live
// check while a judge still reported it. The folder check beside it is there because that was the first
// suspicion, and it holds too: a file glob reads the same path in a mark as in a judge.
func TestEveryEngineARuleJudgesIsAskedAboutAnEditedFile(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data := t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("JOURNAL_ROOT", root)

	files := map[string]string{
		".commandments/config.json": `{"paths": ["shop"], "detectors": ["DocumentedAnywhereDetector", "DocumentedInShopDetector", "DocumentedInTwoLanguagesDetector"]}`,
		".commandments/custom/DocumentedAnywhereDetector.json": `{"engine": "python",
 "sin": {"name": "documented-anywhere", "description": "a documented method", "skill": "python/absence"},
 "find": {"select": "function", "where": [{"documented": true}]}}`,
		".commandments/custom/DocumentedInShopDetector.json": `{"engine": "python",
 "sin": {"name": "documented-in-shop", "description": "a documented method under shop", "skill": "python/absence"},
 "find": {"select": "function", "where": [{"all": [{"documented": true}, {"file": "shop/**"}]}]}}`,
		".commandments/custom/DocumentedInTwoLanguagesDetector.json": `{"engine": ["go", "python"],
 "sin": {"name": "documented-in-two-languages", "description": "a documented method, in either language", "skill": "python/absence"},
 "find": {"select": "function", "where": [{"documented": true}]}}`,
		"shop/__init__.py": "",
		"shop/order.py": `class Order:
    def total(self):
        """Says the total."""
        return 0
`,
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
			"path": "shop/order.py", "kind": "edit", "added": 4, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 2, "text": "    def total(self):"},
				map[string]any{"line_type": "add", "line": 3, "text": `        """Says the total."""`},
			},
		},
	}, data)

	told := tells(t, data)

	if !strings.Contains(told, "documented-anywhere") {
		t.Fatalf("the mark names neither rule, so the test proves nothing:\n%s", told)
	}

	if !strings.Contains(told, "documented-in-shop") {
		t.Errorf("the mark drops the rule that names the folder it applies to:\n%s", told)
	}

	if !strings.Contains(told, "documented-in-two-languages") {
		t.Errorf("the mark drops the rule that judges two engines, asking only the first:\n%s", told)
	}
}
