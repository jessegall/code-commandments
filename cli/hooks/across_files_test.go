package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestAProjectsRuleThatFollowsAChainOfParentsIsNotAskedAboutOneFile(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data := t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("JOURNAL_ROOT", root)

	files := map[string]string{
		".commandments/config.json": `{"paths": ["src"], "detectors": ["HelperInAKindFolderDetector", "NumberedMigrationDetector"]}`,
		".commandments/custom/HelperInAKindFolderDetector.json": `{"engine": "python",
 "sin": {"name": "helper-in-a-kind-folder", "description": "a class sits among the classes of a kind folder it is not", "skill": "python/absence"},
 "find": {"select": "type-declaration", "where": [{"fileMatches": "/migrations/"}, {"not": {"extendsAny": "Migration"}}]}}`,
		".commandments/custom/NumberedMigrationDetector.json": `{"engine": "python",
 "sin": {"name": "numbered-migration", "description": "a migration says its number in its name", "skill": "python/absence"},
 "find": {"select": "type-declaration", "where": [{"nameLike": "Drop*"}]}}`,
		"src/base/migration.py": "class Migration:\n    number = 0\n",
		"src/base/rebuild_migration.py": `from base.migration import Migration


class RebuildMigration(Migration):
    statements: list[str] = []
`,
		"src/migrations/m004_drop_fades.py": `from base.rebuild_migration import RebuildMigration


class DropFadesMigration(RebuildMigration):
    number = 4
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
			"path": "src/migrations/m004_drop_fades.py", "kind": "edit", "added": 5, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 1, "text": "from base.rebuild_migration import RebuildMigration"},
				map[string]any{"line_type": "add", "line": 4, "text": "class DropFadesMigration(RebuildMigration):"},
				map[string]any{"line_type": "add", "line": 5, "text": "    number = 4"},
			},
		},
	}, data)

	queued := drained(t, data)
	if len(queued) != 1 {
		t.Fatalf("the judge left %d answer(s): %v", len(queued), queued)
	}

	told := asText(queued[0]["whisper"])

	if strings.Contains(told, "helper-in-a-kind-folder") {
		t.Errorf("the mark names a sin the file alone cannot answer for:\n%s", told)
	}

	if !strings.Contains(told, "numbered-migration") {
		t.Errorf("the mark does not name the sin this file does answer for:\n%s", told)
	}
}
