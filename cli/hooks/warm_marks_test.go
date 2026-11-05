package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
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

	kept.Hold(project, branch, []string{root}, codebase, nil)
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

// TestOnlyTheServiceReadsABase holds the one process rule. A run started for one hook answers and ends, so a
// tree it began reading would cost a worker's CPU for a quarter of a minute and be thrown away unused; only the
// service, which outlives every hook it answers, reads one.
func TestOnlyTheServiceReadsABase(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(root)
	kept.Drop(project, branch)

	Warming(root, settings)
	if warming(t) {
		t.Error("a run that is not the service began reading a base")
	}

	Serves()
	t.Cleanup(func() { serving = false })

	Warming(root, settings)
	if !warming(t) {
		t.Error("the service did not begin reading a base")
	}

	// The read runs in the background, in a folder this test is about to have deleted under it, so the test
	// waits for it rather than racing its own cleanup.
	for until := time.Now().Add(time.Minute); warming(t) && time.Now().Before(until); {
		time.Sleep(20 * time.Millisecond)
	}
}

// warming says whether a read of a base is running, which is what the keeper is told to start.
func warming(t *testing.T) bool {
	t.Helper()

	running := false
	reading.Range(func(_, _ any) bool {
		running = true

		return false
	})

	return running
}

// TestAnEditOfAFileThatIsGoneIsAnsweredWithNothing holds the mark against a moment whose file no longer exists:
// the agent removed it after the edit, or its worktree was taken away, and the event still arrives. There is
// nothing to judge, so the answer is nothing, and it must not be a sin reported from the tree's memory of the
// file nor a crash on the way.
func TestAnEditOfAFileThatIsGoneIsAnsweredWithNothing(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	gone := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")

	// It is found while it is there, so what follows is about the file being gone and not about the rule.
	rules := allRules(t, root, settings)
	if marks, _, whole := AgainstTheProgram(Marks(root), gone, rules, git.ChangedLines{}, root); !whole || len(marks) == 0 {
		t.Fatalf("the sin is not found while the file is there: judged whole %v, %d marks", whole, len(marks))
	}

	if err := os.Remove(gone); err != nil {
		t.Fatal(err)
	}

	marks, _, whole := AgainstTheProgram(Marks(root), gone, rules, git.ChangedLines{}, root)

	if len(marks) != 0 {
		t.Errorf("a file that is gone carries %d marks, reported from the tree's memory of it", len(marks))
	}

	if whole {
		t.Error("a file that is gone was judged against the program as though it were there")
	}
}

// TestAProjectReachedThroughALinkIsStillJudgedAgainstItsProgram holds the one thing that makes an edit answer
// from the file alone without a word: a path that is not the one the tree holds. A scan resolves the links in
// every path it reads, and the path an event names is joined to the root as given, so a project reached through
// a symlinked path — /tmp on a Mac is one, and so is any checkout under a linked home — gave the tree a path it
// does not hold. The file then reads as one the program never saw, and the rules that read more than one file
// are quietly left out of the answer.
func TestAProjectReachedThroughALinkIsStillJudgedAgainstItsProgram(t *testing.T) {
	root, _ := migrationProject(t)

	// The same project, reached through a link, which is how a path arrives when anything in it is linked.
	linked := filepath.Join(t.TempDir(), "project")
	if err := os.Symlink(root, linked); err != nil {
		t.Skipf("this filesystem has no links: %v", err)
	}

	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	// The path the event names, which is relative, read against the linked root: exactly what a journal running
	// in a linked folder hands over.
	through := judgedFile(linked, "shop/migrations/m004_drop_fades.py", settings)
	if through == "" {
		t.Fatal("a file of the project reached through a link is judged as nothing at all")
	}

	marks, _, whole := AgainstTheProgram(Marks(linked), through, allRules(t, root, settings), git.ChangedLines{}, linked)

	if !whole {
		t.Error("a project reached through a link is not judged against its program at all, and nothing says so")
	}

	if len(marks) == 0 {
		t.Error("the sin in a file reached through a link is not marked")
	}
}

// TestADetachedHeadIsNoBranch holds the base against the state a main checkout is in during a rebase. git calls
// a detached HEAD by the literal name HEAD, and taken for a branch it does two wrong things at once: every
// detached commit of a project shares one base, and a worktree answered from it is diffed against the name
// HEAD, which resolves to the WORKER's own tip, so the worker's committed work reads as no difference at all.
// That is a green tick over everything it wrote.
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

// TestTheEditThatFindsTheTreeDriftedTakesItIn holds what an edit does when it finds OTHER files moved under the
// tree. It used to give the tree up for them and be answered on its own file alone; that could not survive
// several agents at all, since twelve of them writing meant every edit found somebody else's file moved, gave
// the tree up, lost every rule that reads more than one file, and started a 25 to 55 second read that the next
// edit gave up again.
//
// A bridge's cost is per ask and not per file, so those files are read together and taken INTO the tree: the
// edit is answered against the program, and the tree is still held after it.
func TestTheEditThatFindsTheTreeDriftedTakesItIn(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	// Held through a clock already past a sweep, so the first question sweeps the whole tree.
	kept.Clocked(func() time.Time { return time.Now().Add(-2 * warm.Sweep) })
	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Clocked(time.Now); kept.Drop(project, branch) })

	// Another file moved, which nothing announced: the parent of the very class the rule follows.
	elsewhere := filepath.Join(root, "shop", "rebuild_migration.py")
	if err := os.WriteFile(elsewhere, []byte(`from shop.migration import Migration


class RebuildMigration(Migration):
    statements: list[str] = []
    paid = True
`), 0o644); err != nil {
		t.Fatal(err)
	}

	edited := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")

	marks, _, whole := AgainstTheProgram(Marks(root), edited, allRules(t, root, settings), git.ChangedLines{}, root)
	if !whole {
		t.Error("the edit that found the tree drifted was answered on its own file, so every cross-file rule was dropped")
	}

	// And the rule whose chain crosses three files still answers, which is what being judged against the
	// program means.
	if len(marks) == 0 {
		t.Error("the sin whose chain of parents crosses three files is not found, so the program was not read")
	}

	if _, held := kept.Holds(project, branch); !held {
		t.Error("the tree was given up for a file that moved, instead of taking it in")
	}
}
