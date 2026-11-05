package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

// TestAnEditIsJudgedAgainstTheProgramWhereItsBranchHasABase is the other half of the per-file mark. A rule that
// follows a chain of parents cannot answer from the edited file: the class two files away is where the answer
// is. Judged against a view of the branch's base it answers about the real program, so the mark carries the sin;
// with no base held, the same rule is not asked at all and the mark is silent about it.
//
// The sin here is one only a cross-file read can FIND, which is the honest test of the path: the class names
// RebuildMigration as its parent, and only Migration above that makes it a migration.
func TestAnEditIsJudgedAgainstTheProgramWhereItsBranchHasABase(t *testing.T) {
	root, data := migrationProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)
	if project == "" || branch == "" {
		t.Fatalf("the project is %q at branch %q", project, branch)
	}

	edit := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 78,
		"data": map[string]any{
			"path": "shop/migrations/m004_drop_fades.py", "kind": "edit", "added": 2, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 4, "text": "class DropFadesMigration(RebuildMigration):"},
				map[string]any{"line_type": "add", "line": 5, "text": "    number = 4"},
			},
		},
	}

	kept.Drop(project, branch)
	Judge4Into(edit, data)

	if told := tells(t, data); strings.Contains(told, "migration-by-its-parents") {
		t.Errorf("with no base held the mark names a sin the file alone cannot find:\n%s", told)
	}

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase)
	t.Cleanup(func() { kept.Drop(project, branch) })

	// The record of what has been announced is the plugin's, and it was written by the run above; a fresh
	// folder is this run's own, so the sin is new again.
	fresh := t.TempDir()
	t.Setenv(pluginData, fresh)
	Judge4Into(edit, fresh)

	told := tells(t, fresh)
	if !strings.Contains(told, "migration-by-its-parents") {
		t.Errorf("judged against the base, the mark does not name the sin:\n%s", told)
	}
}

// migrationProject is a python project whose migration extends Migration through RebuildMigration, two files
// away, with the rule that finds it by its parents, and the plugin's data folder.
func migrationProject(t *testing.T) (root, data string) {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data = t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("JOURNAL_ROOT", root)

	files := map[string]string{
		".commandments/config.json": `{"paths": ["shop"], "detectors": ["MigrationByItsParentsDetector"]}`,
		".commandments/custom/MigrationByItsParentsDetector.json": `{"engine": "python",
 "sin": {"name": "migration-by-its-parents", "description": "a class the chain of its parents says is a migration", "skill": "python/absence"},
 "find": {"select": "type-declaration", "where": [{"fileMatches": "/migrations/"}, {"extendsAny": "Migration"}]}}`,
		"shop/__init__.py": "",
		"shop/migration.py": `class Migration:
    number = 0
`,
		"shop/rebuild_migration.py": `from shop.migration import Migration


class RebuildMigration(Migration):
    statements: list[str] = []
`,
		"shop/migrations/__init__.py": "",
		"shop/migrations/m004_drop_fades.py": `from shop.rebuild_migration import RebuildMigration


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

	// A branch is what a base is keyed by, and a repository with no commit is on none.
	for _, command := range [][]string{
		{"init", "-q"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	return root, data
}

// tells is every answer the judge queued, as the agent is told it.
func tells(t *testing.T, data string) string {
	t.Helper()

	var said []string
	for _, answer := range drained(t, data) {
		said = append(said, asText(answer["tell"]))
	}

	return strings.Join(said, "\n")
}
