package hooks

import (
	"slices"
	"github.com/jessegall/code-commandments/detectors"
	"fmt"
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
	t.Cleanup(func() { serving.Store(false) })

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

// TestAChangeNobodyAnnouncedIsStillJudged holds the tree against the one change nothing tells it about. A shell
// line that rewrites many files at once — a merge, a rebase, a formatter over the tree — raises no edit of its
// own: the journal's own capture stays silent past thirty-two files, and nothing else announces one either. The
// tree learns of it only by LOOKING.
//
// So the next edit after such a change must be judged against the tree as it now is. Getting this wrong has no
// visible sign: a rule that reads a parent or a caller simply answers about code that is two commits old.
func TestAChangeNobodyAnnouncedIsStillJudged(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	// The clock is already past a sweep, so the first question after the change looks at the whole tree.
	kept.Clocked(func() time.Time { return time.Now().Add(-2 * warm.Sweep) })
	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Clocked(time.Now); kept.Drop(project, branch) })

	// A shell line rewrites the whole chain of parents the rule follows, and announces nothing at all.
	for path, text := range map[string]string{
		"shop/migration.py": "class Migration:\n    number = 0\n    paid = True\n",
		"shop/rebuild_migration.py": `from shop.migration import Migration


class RebuildMigration(Migration):
    statements: list[str] = []
    paid = True
`,
	} {
		if err := os.WriteFile(filepath.Join(root, path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	// And then one file is edited, which is the only thing anybody is told about.
	edited := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")
	if err := os.WriteFile(edited, []byte(`from shop.rebuild_migration import RebuildMigration


class DropFadesMigration(RebuildMigration):
    number = 4
    paid = False
`), 0o644); err != nil {
		t.Fatal(err)
	}

	marks, _, whole := AgainstTheProgram(Marks(root), edited, allRules(t, root, settings), git.Everywhere(), root)

	// Judged against the program, with the files nobody announced taken in: the rule's chain of parents runs
	// through both of them, so it can only answer if the tree looked.
	if !whole {
		t.Fatal("the edit after a change nobody announced is not judged against the program at all")
	}

	if len(marks) == 0 {
		t.Error("the sin whose chain of parents runs through the files nobody announced is not found, so the tree " +
			"answered from the version before them")
	}
}

// TestAMergeSizedChangeIsTakenIntoTheTreeRatherThanGivenUp holds the one number that decides what a merge costs
// an agent. A shell line that rewrites a hundred files announces nothing, so the tree finds them at its next
// sweep, and then it either reads them or gives itself up. Giving up means a re-read — 25 seconds for 1518 files
// and 55 for 5286 — and until it lands every edit is judged on its own file alone with every cross-file rule
// quietly left out. Nothing says so: the only sign is the answers going thin.
//
// Reading them instead costs one ask, and an ask is nearly all fixed cost. Measured here on a 1000-file tree
// (TestWhatAnAskCostsByItsFiles): one file 849ms, 9 files 932ms, 25 files 1.05s, 100 files 1.97s, 200 files
// 2.48s. So a hundred files cost about a second more than one, once, against a minute of thin answers — which is
// why the cap is what the service splices for a judging and not the 25 it used to be.
func TestAMergeSizedChangeIsTakenIntoTheTreeRatherThanGivenUp(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	// A hundred files of the project's own, there before the tree is read, so the change below is a change to
	// files the base HOLDS rather than files it never saw.
	var merged []string
	for at := 0; at < 100; at++ {
		path := filepath.Join(root, "shop", fmt.Sprintf("part%03d.py", at))
		if err := os.WriteFile(path, []byte(fmt.Sprintf("class Part%03d:\n    number = 0\n", at)), 0o644); err != nil {
			t.Fatal(err)
		}

		merged = append(merged, path)
	}

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	// The clock already past a sweep, so the first question after the merge looks at the whole tree, as it does
	// within half a minute of a real one.
	kept.Clocked(func() time.Time { return time.Now().Add(-2 * warm.Sweep) })
	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Clocked(time.Now); kept.Drop(project, branch) })

	// The merge: every one of them rewritten, with nothing announced about any of it.
	time.Sleep(10 * time.Millisecond)
	for at, path := range merged {
		if err := os.WriteFile(path, []byte(fmt.Sprintf("class Part%03d:\n    number = 1\n    paid = True\n", at)), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	// And the chain of parents the rule follows rewritten with them, so the edit below can only be answered by a
	// tree that took the merge in.
	for path, text := range map[string]string{
		"shop/migration.py": "class Migration:\n    number = 0\n    paid = True\n",
		"shop/rebuild_migration.py": `from shop.migration import Migration


class RebuildMigration(Migration):
    statements: list[str] = []
    paid = True
`,
	} {
		if err := os.WriteFile(filepath.Join(root, path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	edited := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")
	if err := os.WriteFile(edited, []byte(`from shop.rebuild_migration import RebuildMigration


class DropFadesMigration(RebuildMigration):
    number = 4
    paid = False
`), 0o644); err != nil {
		t.Fatal(err)
	}

	marks, _, whole := AgainstTheProgram(Marks(root), edited, allRules(t, root, settings), git.Everywhere(), root)

	if !whole {
		t.Fatal("a hundred files changed with nothing announced cost the tree itself, so every edit until the " +
			"re-read lands is judged on its own file alone")
	}

	if len(marks) == 0 {
		t.Error("the sin whose chain of parents runs through the merged files is not found, so the tree answered " +
			"from the version before the merge")
	}

	// And the tree is still held, which is the whole claim: it was patched rather than given up.
	if _, held := kept.Holds(project, branch); !held {
		t.Error("the tree was given up for a merge it could have read in one ask")
	}
}

// TestAWorkersEditIsJudgedAgainstItsOwnAgentsTree holds the EDIT path to the same tree the judging path answers
// from. An edit is what an agent feels: it writes a file and waits to be told. Judged against no tree it is
// judged on its own file alone, with every rule that reads a parent, a caller or a recurrence quietly dropped,
// and nothing says so — the only sign is the answers going thin.
//
// A worker stands on a branch of its own, cut from the branch its main agent works on, so the tree it answers
// from is that agent's. Looked up by the branch the project checkout happens to be standing on at that instant,
// the lookup found nothing as soon as that checkout moved: the judging path was fixed for this and the edit path
// was not, which is the path that matters most.
func TestAWorkersEditIsJudgedAgainstItsOwnAgentsTree(t *testing.T) {
	root, _ := workerProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	roots, err := config.DeclaredRoots(root)
	if err != nil || len(roots) == 0 {
		t.Fatalf("the project declares no root: %v", err)
	}

	codebase, err := scan.Walk(roots, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, roots, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	// And then the main checkout moves, as a main agent does. Nothing about the worker changed.
	if out, err := exec.Command("git", "-C", root, "checkout", "-q", "-b", "the-main-agents-own-work").CombinedOutput(); err != nil {
		t.Fatalf("git checkout: %s", out)
	}

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	edited := filepath.Join(worktree, "src", "order.py")

	// The lookup by the main checkout's CURRENT branch must really miss, or this test would pass whatever the
	// edit path does with it.
	if _, _, byBranch := baseFor(worktree); byBranch {
		t.Fatalf("the tree is still found by the branch the project is on, %q, so there is nothing to prove",
			git.CurrentBranch(project))
	}

	_, _, whole := AgainstTheProgram(Marks(root), edited, allRules(t, root, settings), git.Everywhere(), worktree)
	if !whole {
		t.Error("a worker's edit is judged on its own file alone after the main checkout moved off the branch it " +
			"was cut from, so every rule that reads more than one file is dropped and nothing says so")
	}
}

// TestJudgingSeveralFilesTogetherFindsWhatJudgingThemOneAtATimeFinds is what makes a batch safe to trust. An
// agent's edits are judged in ONE pass over one view instead of a pass each, because the pass is the cost:
// measured with eight agents answered from a warm tree with no reading at all, a judging of one file took 3.7
// seconds of rules, and an agent's twelve edits judged one after another cost it 44 seconds.
//
// The thing that goes wrong in a change like this is the attribution, and getting it wrong tells one agent about
// another agent's file. So the property held here is equality: for every file, the marks found in one pass are
// the marks found by asking about that file alone.
func TestJudgingSeveralFilesTogetherFindsWhatJudgingThemOneAtATimeFinds(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	// Several files of one batch, two with a sin of their own and one with none: a file the batch judges and
	// finds nothing in must come back with nothing rather than with its neighbour's marks.
	written := map[string]string{
		// One of them holds a sin whose rule has a NAME SHARED with another engine's, which is what the union
		// nearly lost: of 247 rules a project had enabled only 127 names were distinct, and keyed by the name
		// this set kept the PHP rule of each name and dropped the python one. With a fixture holding nothing
		// such a rule could find, this test passed through the whole of that.
		"shop/migrations/m101_one.py": `from shop.rebuild_migration import RebuildMigration


class OneMigration(RebuildMigration):
    number = 101

    def deepened(self, order, rate=None):
        if order is not None:
            for line in order.lines:
                if line.total > 0:
                    if rate is not None:
                        return line.total * rate
                    else:
                        return line.total
        return 0
`,
		"shop/migrations/m102_two.py": `from shop.rebuild_migration import RebuildMigration


class TwoMigration(RebuildMigration):
    number = 102
`,
		"shop/migrations/m103_clean.py": "NUMBER = 103\n",
	}

	var files []string
	for under, text := range written {
		whole := filepath.Join(root, under)
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}

		files = append(files, whole)
	}
	slices.Sort(files)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	rules := allRules(t, root, settings)
	together := marksOverTogether(codebase, Marks(root), files,
		func(string) []detectors.Detector { return rules },
		func(string) git.ChangedLines { return git.Everywhere() })

	// CONTAINMENT and not equality. Every sin the file's own reading finds must be found by the batch too; the
	// batch may find MORE, and that is right rather than wrong: with several of an agent's files open at once, a
	// rule that reads a recurrence or a twin can see them repeating one another, which no reading of one file
	// can. Measured on three files written alike, two marks each alone and three each together.
	var found int
	for _, file := range files {
		alone := marksOver(codebase, Marks(root), file, rules, git.Everywhere())
		found += len(alone)

		said := map[string]bool{}
		for _, mark := range together[file] {
			said[mark.Found()] = true
		}

		for _, mark := range alone {
			if !said[mark.Found()] {
				t.Errorf("%s: judged alone it holds %q and the batch does not, so that sin goes untold",
					filepath.Base(file), mark.Found())
			}
		}
	}

	if found == 0 {
		t.Fatal("neither reading found a sin, so the two agree about nothing and this proves nothing")
	}

	// And the sin whose rule shares its name with another engine's is among them, or this fixture has stopped
	// guarding the thing it was given that nest for.
	var said string
	for _, mark := range together[filepath.Join(root, "shop", "migrations", "m101_one.py")] {
		said += mark.Found() + "\n"
	}

	if !strings.Contains(said, "deep-python-nesting") {
		t.Errorf("the batch does not name deep-python-nesting, whose rule shares its name with another engine's:"+
			"\n%s", said)
	}

	// And nothing is attributed to a file the batch was never asked about.
	for file := range together {
		if !slices.Contains(files, file) {
			t.Errorf("the batch attributed marks to %s, which it was not asked about", file)
		}
	}
}
