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

func TestAnEditIsJudgedOnItsOwnEvenWhereItsBranchHasABase(t *testing.T) {
	root, data := migrationProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)
	if project == "" || branch == "" {
		t.Fatalf("the project is %q at branch %q", project, branch)
	}

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	base := kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	Judge4Into(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 78,
		"data": map[string]any{
			"path": "shop/migrations/m004_drop_fades.py", "kind": "edit", "added": 2, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 4, "text": "class DropFadesMigration(RebuildMigration):"},
				map[string]any{"line_type": "add", "line": 5, "text": "    number = 4"},
			},
		},
	}, data)

	if told := tells(t, data); strings.Contains(told, "migration-by-its-parents") {
		t.Errorf("an edit was judged against the held tree, by a rule that reads across files:\n%s", told)
	}

	if held, _ := kept.Holds(project, branch); held != base {
		t.Error("an edit patched the held tree, so the tree is kept up by edits it is not judged against")
	}
}

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

func tells(t *testing.T, data string) string {
	t.Helper()

	var said []string
	for _, answer := range drained(t, data) {
		said = append(said, asText(answer["whisper"]))
	}

	return strings.Join(said, "\n")
}

func warming(t *testing.T) bool {
	t.Helper()

	running := false
	reading.Range(func(_, _ any) bool {
		running = true

		return false
	})

	return running
}

func TestADetachedHeadIsNoBranch(t *testing.T) {
	root, _ := migrationProject(t)

	if _, ok := rootOf(root); !ok {
		t.Fatal("a project on a branch has no base to answer from")
	}

	said, err := exec.Command("git", "-C", root, "rev-parse", "HEAD").CombinedOutput()
	if err != nil {
		t.Fatalf("git rev-parse: %s", said)
	}

	gitIn(t, root, "checkout", "-q", strings.TrimSpace(string(said)))

	if branch := git.CurrentBranch(root); branch != "HEAD" {
		t.Skipf("this git names a detached head %q, so the test says nothing", branch)
	}

	if _, ok := rootOf(root); ok {
		t.Error("a detached head is taken for a branch, so a worker is diffed against its own tip and told it wrote nothing")
	}
}
