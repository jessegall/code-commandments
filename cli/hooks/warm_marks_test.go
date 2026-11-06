package hooks

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/detectors"
)

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

	fresh := t.TempDir()
	t.Setenv(pluginData, fresh)
	Judge4Into(edit, fresh)

	told := tells(t, fresh)
	if !strings.Contains(told, "migration-by-its-parents") {
		t.Errorf("judged against the base, the mark does not name the sin:\n%s", told)
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
		said = append(said, asText(answer["tell"]))
	}

	return strings.Join(said, "\n")
}

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

	for until := time.Now().Add(time.Minute); warming(t) && time.Now().Before(until); {
		time.Sleep(20 * time.Millisecond)
	}
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

func TestAProjectReachedThroughALinkIsStillJudgedAgainstItsProgram(t *testing.T) {
	root, _ := migrationProject(t)

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

func TestTheEditThatFindsTheTreeDriftedTakesItIn(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Clocked(func() time.Time { return time.Now().Add(-2 * warm.Sweep) })
	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Clocked(time.Now); kept.Drop(project, branch) })

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

	if len(marks) == 0 {
		t.Error("the sin whose chain of parents crosses three files is not found, so the program was not read")
	}

	if _, held := kept.Holds(project, branch); !held {
		t.Error("the tree was given up for a file that moved, instead of taking it in")
	}
}

func TestAChangeNobodyAnnouncedIsStillJudged(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Clocked(func() time.Time { return time.Now().Add(-2 * warm.Sweep) })
	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Clocked(time.Now); kept.Drop(project, branch) })

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
		t.Fatal("the edit after a change nobody announced is not judged against the program at all")
	}

	if len(marks) == 0 {
		t.Error("the sin whose chain of parents runs through the files nobody announced is not found, so the tree " +
			"answered from the version before them")
	}
}

func TestAMergeSizedChangeIsTakenIntoTheTreeRatherThanGivenUp(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

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

	kept.Clocked(func() time.Time { return time.Now().Add(-2 * warm.Sweep) })
	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Clocked(time.Now); kept.Drop(project, branch) })

	time.Sleep(10 * time.Millisecond)
	for at, path := range merged {
		if err := os.WriteFile(path, []byte(fmt.Sprintf("class Part%03d:\n    number = 1\n    paid = True\n", at)), 0o644); err != nil {
			t.Fatal(err)
		}
	}

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

	if _, held := kept.Holds(project, branch); !held {
		t.Error("the tree was given up for a merge it could have read in one ask")
	}
}

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

	if out, err := exec.Command("git", "-C", root, "checkout", "-q", "-b", "the-main-agents-own-work").CombinedOutput(); err != nil {
		t.Fatalf("git checkout: %s", out)
	}

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	edited := filepath.Join(worktree, "src", "order.py")

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

func TestJudgingSeveralFilesTogetherFindsWhatJudgingThemOneAtATimeFinds(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	written := map[string]string{
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

	var said string
	for _, mark := range together[filepath.Join(root, "shop", "migrations", "m101_one.py")] {
		said += mark.Found() + "\n"
	}

	if !strings.Contains(said, "deep-python-nesting") {
		t.Errorf("the batch does not name deep-python-nesting, whose rule shares its name with another engine's:"+
			"\n%s", said)
	}

	for file := range together {
		if !slices.Contains(files, file) {
			t.Errorf("the batch attributed marks to %s, which it was not asked about", file)
		}
	}
}

func TestEveryWayTheJudgingNarrowsSaysWhichItWas(t *testing.T) {
	for why, said := range map[Narrowed]string{
		noTreeHeld:     "no tree",
		filesNotOnDisk: "not on disk",
		tooMuchMoved:   "more files changed",
		patchFailed:    "brought up to",
		parseFailed:    "could not be parsed",
		notInTheTree:   "not in the tree",
	} {
		if why == "" {
			t.Error("a reason reads as the whole program having been asked")
		}
		if !strings.Contains(string(why), said) {
			t.Errorf("the reason %q does not say %q", why, said)
		}
	}

	root := t.TempDir()
	file := filepath.Join(root, "order.py")
	if err := os.WriteFile(file, []byte("def total(order):\n    return order.total\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if _, _, why := AgainstTheProgramNarrowed(Marks(root), nil, nil, nil, root); why != filesNotOnDisk {
		t.Errorf("a judging of no files at all answers %q", why)
	}

	gone := []string{filepath.Join(root, "never-written.py")}
	if _, _, why := AgainstTheProgramNarrowed(Marks(root), gone, func(string) []detectors.Detector { return nil },
		func(string) git.ChangedLines { return git.ChangedLines{} }, root); why == "" {
		t.Error("a judging of a file that is not on disk answers as though it had asked everything")
	}
}

func TestAWorktreeIsJudgedAgainstItsOwnCopyOfWhatTheProjectHasUncommitted(t *testing.T) {
	root, _ := workerProject(t)
	worker := filepath.Join(root, ".journal", "worktrees", "worker-1")
	named, _ := rootOf(root)

	own := filepath.Join(root, "src", "order.py")
	if err := os.WriteFile(own, []byte("class Order:\n    def total(self):\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	over := kept.Over(worker)
	if why := fill(over, named, nil...); why != "" {
		t.Fatalf("the worktree's overlay could not be filled: %s", why)
	}

	if state, _, _ := over.Filling(); state != warm.Filled {
		t.Fatalf("a filled overlay answers %v", state)
	}

	stands := filepath.Join(worker, "src", "order.py")

	held := false
	for _, stream := range over.Streams() {
		for _, file := range stream.Files {
			if file.Path == stands {
				held = true
			}
		}
	}

	if !held {
		t.Errorf("the worktree's own copy of %s is not in its overlay, so it would be judged against the project's uncommitted text", stands)
	}
}

func TestBothDoorsRefuseTheSameWorktreeForTheSameReason(t *testing.T) {
	root, _ := workerProject(t)
	worker := filepath.Join(root, ".journal", "worktrees", "worker-1")
	project := git.ProjectRoot(root)

	kept.Drop(project, git.CurrentBranch(project))
	kept.Forget(worker)

	named, base, why := answering(root, worker)
	if why == "" || base != nil {
		t.Fatalf("a worktree with no tree held and no overlay was answered from %v", named)
	}

	if _, _, projects := answering(root, project); projects != why {
		t.Errorf("the two doors answer the same worktree differently: %q against %q", why, projects)
	}

	if _, _, why := answering(root, filepath.Join(root, ".journal", "worktrees", "worker-404")); why != checkoutGone {
		t.Errorf("a checkout that is gone answers %q", why)
	}
}

func TestTheThreeCheckoutsAJudgingCannotAnswerAreToldApart(t *testing.T) {
	root, _ := workerProject(t)
	worker := filepath.Join(root, ".journal", "worktrees", "worker-1")
	project := git.ProjectRoot(root)

	kept.Drop(project, git.CurrentBranch(project))
	kept.Forget(worker)

	if _, _, why := answering(root, filepath.Join(root, ".journal", "worktrees", "worker-404")); why != checkoutGone {
		t.Errorf("a checkout that is not on disk answers %q", why)
	}

	if _, _, why := answering(root, worker); why == "" || why == checkoutGone {
		t.Errorf("a checkout nobody holds a tree for answers %q", why)
	}

	over := kept.Over(worker)
	over.CouldNotFill("git could not list it")
	if state, _, why := over.Filling(); state != warm.FillingFailed || why == "" {
		t.Errorf("a reading that failed answers %v because %q", state, why)
	}
}

func TestManyWarmingsAtOnceReadTheTreeOnce(t *testing.T) {
	root, _ := workerProject(t)
	project := git.ProjectRoot(root)
	named, _ := rootOf(root)

	kept.Drop(project, named.branch)
	Serves()

	settings, _ := config.Load(root)

	reads := 0
	for range 5 {
		if _, already := reading.LoadOrStore(named.key(), true); !already {
			reads++
		}
	}

	if reads != 1 {
		t.Errorf("five warmings at once started %d reads of the tree", reads)
	}

	reading.Delete(named.key())

	Warming(root, settings)
	if _, running := reading.Load(named.key()); !running {
		t.Skip("the read of the tree finished before this test could see it running")
	}

	Warming(root, settings)
	Warming(root, settings)

	for until := time.Now().Add(time.Minute); warming(t) && time.Now().Before(until); {
		time.Sleep(20 * time.Millisecond)
	}

	if warming(t) {
		t.Error("a read of the tree is still running a minute later, and the project it reads is about to be removed")
	}
}
