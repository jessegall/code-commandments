package hooks

import (
	"fmt"
	"io/fs"
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

func TestAJudgingAskedForOnTheSocketIsAnsweredFromTheTreeTheServiceHolds(t *testing.T) {
	root, _ := migrationProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	answer, asked := warm.Asked(root, warm.Request{
		Judge: []string{root, "--no-checklist", "--sin=migration-by-its-parents"},
		In:    root,
	})
	if !asked {
		t.Fatal("the run found no service on the socket named from the project")
	}

	for _, said := range []string{"MigrationByItsParentsDetector", "m004_drop_fades.py:4"} {
		if !strings.Contains(answer.Out, said) {
			t.Errorf("the answer does not name %s:\n%s%s", said, answer.Out, answer.Err)
		}
	}

	if answer.Code != 1 {
		t.Errorf("the answer exits %d, want 1 for a run that found a sin", answer.Code)
	}
}

func TestARunFindsNoServiceWhereNoneListens(t *testing.T) {
	root, _ := migrationProject(t)

	if _, asked := warm.Asked(root, warm.Request{Judge: []string{root}, In: root}); asked {
		t.Error("a run was answered by a service nobody started")
	}
}

func TestTheAnswerSaysHowLongThePluginTookOverTheEvent(t *testing.T) {
	root, data := migrationProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	Judge4Into(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 78,
		"data": map[string]any{
			"path": "shop/migrations/m004_drop_fades.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 4, "text": "class DropFadesMigration(RebuildMigration):"},
			},
		},
	}, data)

	queued := drained(t, data)
	if len(queued) != 1 {
		t.Fatalf("the judge left %d answer(s)", len(queued))
	}

	took, said := queued[0]["judged_ms"].(float64)
	if !said || took < 1 {
		t.Errorf("the answer says judged_ms %v, said %v; it should carry the milliseconds the judging took", took, said)
	}

	whole, told := queued[0]["judged_total_ms"].(float64)
	if !told || whole < took {
		t.Errorf("the answer says judged_total_ms %v against a judging of %v, told %v", whole, took, told)
	}

	if whole > float64(10*time.Minute/time.Millisecond) {
		t.Errorf("the answer says it held the event %v ms, which is no duration of one", whole)
	}

	read, shared := queued[0]["read_ms"].(float64)
	if shared && read > took {
		t.Errorf("the answer says read_ms %v of a judging of %v", read, took)
	}
}

func TestAJudgingTheBaseCannotCoverIsDeclined(t *testing.T) {
	root, _ := migrationProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)
	kept.Drop(project, branch)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	if answer, asked := warm.Asked(root, warm.Request{Judge: []string{root, "--no-checklist"}, In: root}); asked {
		t.Errorf("with no base held the service judged it anyway: exit %d\n%s", answer.Code, answer.Out)
	}
}

func TestAWorkersHandInJudgingIsAnsweredFromTheBase(t *testing.T) {
	root, _ := workerProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	answer, asked := warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: worktree})
	if !asked {
		t.Fatal("a worker's hand-in judging was declined, so it reads the whole tree in its own process")
	}

	if answer.Code != 1 {
		t.Errorf("the hand-in exits %d, want 1 for the sin the worker wrote:\n%s%s", answer.Code, answer.Out, answer.Err)
	}

	for _, said := range []string{"DocumentedAnywhereDetector", "order.py:2"} {
		if !strings.Contains(answer.Out, said) {
			t.Errorf("the hand-in's answer does not name %s, so it judged the project's copy and not the worker's:\n%s%s", said, answer.Out, answer.Err)
		}
	}
}

func TestWhatTheShellDidToTheTreeIsJudged(t *testing.T) {
	root, _ := migrationProject(t)
	heldBaseOf(t, root)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	if said := judgingOf(t, root); !strings.Contains(said, "m004_drop_fades.py") {
		t.Fatalf("the sin is not found before anything is done to the tree:\n%s", said)
	}

	gone := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")
	if err := os.Remove(gone); err != nil {
		t.Fatal(err)
	}

	if said := judgingOf(t, root); strings.Contains(said, "m004_drop_fades.py") {
		t.Errorf("a file the shell removed is still judged, from a tree held in memory:\n%s", said)
	}

	made := filepath.Join(root, "shop", "migrations", "m005_drop_tails.py")
	if err := os.WriteFile(made, []byte(`from shop.rebuild_migration import RebuildMigration


class DropTailsMigration(RebuildMigration):
    number = 5
`), 0o644); err != nil {
		t.Fatal(err)
	}

	if said := judgingOf(t, root); !strings.Contains(said, "m005_drop_tails.py") {
		t.Errorf("a file written with no event of its own is judged as though it did not exist:\n%s", said)
	}

	moved := filepath.Join(root, "shop", "migrations", "m006_drop_tails.py")
	if err := os.Rename(made, moved); err != nil {
		t.Fatal(err)
	}

	said := judgingOf(t, root)

	if strings.Contains(said, "m005_drop_tails.py") {
		t.Errorf("a renamed file is judged at the path it no longer has:\n%s", said)
	}

	if !strings.Contains(said, "m006_drop_tails.py") {
		t.Errorf("a renamed file is not judged at the path it now has:\n%s", said)
	}

}

func heldBaseOf(t *testing.T, root string) {
	t.Helper()

	settings, _ := config.Load(root)

	roots, err := config.DeclaredRoots(root)
	if err != nil || len(roots) == 0 {
		t.Fatalf("the project declares no root, so its base would be read over the whole folder: %v", err)
	}

	codebase, err := scan.Walk(roots, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	kept.Hold(project, branch, roots, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })
}

func judgingOf(t *testing.T, root string) string {
	t.Helper()

	answer, asked := warm.Asked(root, warm.Request{
		Judge: []string{root, "--no-checklist", "--sin=migration-by-its-parents"},
		In:    root,
	})
	if !asked {
		t.Fatal("the judging was declined, so it says nothing about the tree the service holds")
	}

	return answer.Out + answer.Err
}

func TestAServedJudgingIsRecordedWithWhereItsTimeWent(t *testing.T) {
	root, data := workerProject(t)
	forgetBookkeeping(t)
	heldBaseOf(t, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	wroteInto(t, filepath.Join(worktree, "src", "order.py"), olderSin)

	judgedFor(warm.Request{Judge: []string{"--no-checklist", "--parallel=1"}, In: worktree}, root)

	said, err := os.ReadFile(filepath.Join(data, "judgings.log"))
	if err != nil {
		t.Fatalf("a judging answered by the service left no line in the log: %v", err)
	}

	for _, part := range []string{"| judge | worktree=" + worktree, "tree=held", "overlay=1", "parsed=1 in", "rules="} {
		if !strings.Contains(string(said), part) {
			t.Errorf("the line does not say %q:\n%s", part, said)
		}
	}
}

func TestAWorktreesChangedFileInAnExcludedFolderIsNotInItsProgram(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	own := filepath.Join(worktree, "src", "order.py")
	excluded := filepath.Join(worktree, "src", "web", "dist", "bundle.py")

	wroteInto(t, own, olderSin)
	wroteInto(t, excluded, olderSin)

	declined := ""

	codebase, err := heldOrDeclined(root, worktree, &declined, new(int))(
		scan.Walk([]string{filepath.Join(worktree, "src")}, source.Under(worktree, []string{"**/dist"})))
	if err != nil || declined != "" {
		t.Fatalf("the judging was not answered from the tree: %v, %s", err, declined)
	}

	if !holds(codebase, own) {
		t.Errorf("the program does not hold the worktree's own changed source %s", own)
	}

	if holds(codebase, excluded) {
		t.Errorf("the program holds %s, which the project excludes", excluded)
	}
}

func TestAFileAWorkerDeletedIsNotInItsProgram(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	own := filepath.Join(worktree, "src", "order.py")
	stands := filepath.Join(root, "src", "order.py")

	reading := heldOrDeclined(root, worktree, new(string), new(int))

	codebase, err := reading(scan.Walk([]string{filepath.Join(worktree, "src")}, source.Excluded{}))
	if err != nil || codebase == nil {
		t.Fatalf("the worker's judging was not answered from the base: %v", err)
	}

	if !holds(codebase, own) {
		t.Errorf("the program does not hold the worker's own file at %s", own)
	}

	if holds(codebase, stands) {
		t.Errorf("the program holds the project's %s as well as the worker's copy of it", stands)
	}

	if err := os.Remove(own); err != nil {
		t.Fatal(err)
	}

	for _, command := range [][]string{
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the worker drops the file"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", worktree}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	codebase, err = reading(scan.Walk([]string{filepath.Join(worktree, "src")}, source.Excluded{}))
	if err != nil || codebase == nil {
		t.Fatalf("the worker's judging was not answered from the base: %v", err)
	}

	if holds(codebase, stands) {
		t.Errorf("the worker deleted the file and the program holds the project's %s in its place", stands)
	}

	if holds(codebase, own) {
		t.Errorf("the program still holds the worker's deleted %s", own)
	}
}

func TestADeclineSaysWhichOfTheThreeMadeTheFilesDiffer(t *testing.T) {
	differs := Differs{
		Moved:  []string{"/p/src/a.py"},
		Unread: []string{"/p/.agents/skills/x.py", "/p/.agents/skills/y.py", "/p/.agents/skills/z.py"},
		Own:    []string{"/p/src/b.py", "/p/src/c.py"},
		Asked:  map[string]string{"/p/.agents/skills/x.py": "/project/.agents/skills/x.py"},
		Tree:   "the tree holds 1200 files of [python], read over [/project/src]",
	}

	said := differs.where()

	for _, named := range []string{
		"1 the tree's own files whose bytes moved",
		"3 files the walk found that the tree never read",
		"2 files this checkout differs from the tree's branch by",
	} {
		if !strings.Contains(said, named) {
			t.Errorf("the decline does not say %q:\n%s", named, said)
		}
	}

	for _, path := range differs.Unread {
		if !strings.Contains(said, path) {
			t.Errorf("the decline names no path of the group it blames:\n%s", said)

			break
		}
	}

	twice := Differs{Moved: []string{"/p/src/a.py"}, Own: []string{"/p/src/a.py"}}
	if read := twice.read(); len(read) != 1 {
		t.Errorf("one file in two of the three groups reads as %v", read)
	}

	if said := (Differs{}).where(); !strings.Contains(said, "none to name") {
		t.Errorf("a judging that differs by nothing says %q", said)
	}

	if !strings.Contains(said, "asked about /project/.agents/skills/x.py") {
		t.Errorf("the decline does not say which path the tree was asked about:\n%s", said)
	}

	if !strings.Contains(said, "read over [/project/src]") {
		t.Errorf("the decline does not say what the tree itself holds:\n%s", said)
	}
}

func TestAWorkersChangesAreJudgedWhereTheServiceAnswersIt(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	for at := 0; at < leastReadInOneGo-50; at++ {
		many := filepath.Join(worktree, "src", fmt.Sprintf("part%03d.py", at))
		if err := os.WriteFile(many, []byte(fmt.Sprintf(`class Part%03d:
    def total(self):
        """Says the total."""
        return 0
`, at)), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	answer, asked := warm.Asked(root, warm.Request{Judge: []string{"--changes", "--no-checklist"}, In: worktree})
	if !asked {
		t.Fatalf("the service declined a worker's judging of %d files, inside the %d it reads for one: %s",
			leastReadInOneGo-50, leastReadInOneGo, answer.Because)
	}

	if strings.Contains(answer.Out, "Nothing to judge") {
		t.Errorf("a worker's uncommitted edit reads as nothing to judge:\n%s%s", answer.Out, answer.Err)
	}

	if !strings.Contains(answer.Out, "order.py") {
		t.Errorf("the worker's own changed file is not in the answer:\n%s%s", answer.Out, answer.Err)
	}

	for _, named := range []string{"part000.py", "part100.py", fmt.Sprintf("part%03d.py", leastReadInOneGo-51)} {
		if !strings.Contains(answer.Out, named) {
			t.Errorf("%s was changed in the worktree and is not in the answer:\n%s%s", named, answer.Out, answer.Err)
		}
	}
}

func TestAPathInsideAWorktreeIsTheOnlyThingJudged(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	elsewhere := filepath.Join(root, "src", "invoice.py")
	if err := os.WriteFile(elsewhere, []byte("class Invoice:\n    def total(self):\n        \"\"\"Says the total.\"\"\"\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	named := filepath.Join(worktree, "src")

	answer, asked := warm.Asked(root, warm.Request{Judge: []string{named, "--no-checklist"}, In: worktree})
	if !asked {
		t.Skipf("the service declined this judging, so what it would have judged was never asked: %s", answer.Because)
	}

	if !strings.Contains(answer.Out, "order.py") {
		t.Errorf("the worker's own sin under the named path is not in the answer:\n%s%s", answer.Out, answer.Err)
	}

	if strings.Contains(answer.Out, "invoice.py") {
		t.Errorf("a run named %s reported a finding in the project's own src, which that path does not cover:\n%s%s",
			named, answer.Out, answer.Err)
	}

	sibling := filepath.Join(worktree, "src", "refund.py")
	if err := os.WriteFile(sibling, []byte("class Refund:\n    def total(self):\n        \"\"\"Says the total.\"\"\"\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	one := filepath.Join(worktree, "src", "order.py")

	answer, asked = warm.Asked(root, warm.Request{Judge: []string{one, "--no-checklist"}, In: worktree})
	if !asked {
		t.Skipf("the service declined the one-file judging: %s", answer.Because)
	}

	if !strings.Contains(answer.Out, "order.py") {
		t.Errorf("a run named one file does not report that file's own sin:\n%s%s", answer.Out, answer.Err)
	}

	if strings.Contains(answer.Out, "refund.py") {
		t.Errorf("a run named %s reported a finding in a sibling of the same checkout:\n%s%s", one, answer.Out, answer.Err)
	}
}

func TestAWorktreesFilesAreNotAllFilesTheTreeNeverRead(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	named, base, held := baseFor(root)
	if !held {
		t.Fatal("no tree is held, so there is nothing to ask")
	}

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	var asked []string
	if err := filepath.WalkDir(filepath.Join(worktree, "src"), func(path string, entry fs.DirEntry, err error) error {
		if err == nil && !entry.IsDir() && strings.HasSuffix(path, ".py") {
			asked = append(asked, path)
		}

		return err
	}); err != nil {
		t.Fatal(err)
	}

	if len(asked) == 0 {
		t.Fatal("the worktree holds no python file, so this proves nothing")
	}

	differs, covered := differingFrom(base, named, checkoutOf(named.project, worktree), asked)
	if !covered {
		t.Fatal("the worktree shares no history with the branch the tree was read at")
	}

	if len(differs.Unread) != 0 {
		t.Errorf("%d of the worktree's %d files count as files the tree never read: %v\nthe base holds the "+
			"project's copy of each at the same path under it, so none of them is new to it",
			len(differs.Unread), len(asked), differs.Unread)
	}
}

func TestAWorkerIsServedInTheShapeTheJournalReallyLaysOut(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	t.Setenv(pluginData, t.TempDir())
	t.Setenv("JOURNAL_ROOT", root)

	for path, text := range map[string]string{
		".commandments/config.json": `{"paths": ["src/core", "src/viewer", "tools/journal-hook"],
 "detectors": ["DocumentedAnywhereDetector"]}`,
		".commandments/custom/DocumentedAnywhereDetector.json": `{"engine": "python",
 "sin": {"name": "documented-anywhere", "description": "a documented method", "skill": "python/absence"},
 "find": {"select": "function", "where": [{"documented": true}]}}`,
		"src/core/__init__.py":           "",
		"src/core/order.py":              "class Order:\n    pass\n",
		"src/viewer/__init__.py":         "",
		"src/viewer/window.py":           "class Window:\n    pass\n",
		"tools/journal-hook/__init__.py": "",
		"tools/journal-hook/hook.py":     "class Hook:\n    pass\n",

		"scripts/release.py":       "class Release:\n    pass\n",
		"docs/examples/sample.py":  "class Sample:\n    pass\n",
		"src/sandbox/throwaway.py": "class Throwaway:\n    pass\n",
		"src/core/Order.cs":        "namespace Shop;\n\npublic class Order {}\n",
		"src/core/Refund.cs":       "namespace Shop;\n\npublic class Refund {}\n",
	} {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	worktree := filepath.Join(root, ".journal", "worktrees", "development", "worker-588")
	for _, command := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
		{"worktree", "add", "-q", "-b", "journal/development/worker-588", worktree},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	if err := os.WriteFile(filepath.Join(worktree, "src", "core", "order.py"), []byte(`class Order:
    def total(self):
        """Says the total."""
        return 0
`), 0o644); err != nil {
		t.Fatal(err)
	}

	heldBaseOf(t, root)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	var asked []string
	if err := filepath.WalkDir(worktree, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || strings.Contains(path, "/.git/") {
			return err
		}
		if strings.HasSuffix(path, ".py") || strings.HasSuffix(path, ".cs") {
			asked = append(asked, path)
		}

		return nil
	}); err != nil {
		t.Fatal(err)
	}

	named, base, held := baseFor(root)
	if !held {
		t.Fatal("no tree is held for the project")
	}

	differs, covered := differingFrom(base, named, checkoutOf(named.project, worktree), asked)
	if !covered {
		t.Fatal("the worktree shares no history with the branch the tree was read at")
	}

	outside := map[string]bool{}
	for _, under := range []string{"scripts/release.py", "docs/examples/sample.py", "src/sandbox/throwaway.py"} {
		outside[filepath.Join(worktree, under)] = true
	}

	counted := map[string]bool{}
	for _, file := range differs.Unread {
		counted[file] = true

		if !outside[file] {
			t.Errorf("%s counts as a file the tree never read, while it lies under a declared path the tree was "+
				"read over (%d counted of %d asked: %v)", file, len(differs.Unread), len(asked), differs.Unread)
		}
	}

	for file := range outside {
		if !counted[file] {
			t.Errorf("%s lies outside every declared path, so the tree never read it, yet nothing counts it: the "+
				"judging would be answered as though that file did not exist", file)
		}
	}

	answer, asking := warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: worktree})
	if !asking {
		t.Fatalf("a worker's hand-in was declined: %s", answer.Because)
	}

	if !strings.Contains(answer.Out, "order.py") {
		t.Errorf("the served answer does not name the worker's own sin:\n%s%s", answer.Out, answer.Err)
	}
}

func TestHowManyFilesAreReadInOneGoIsAFractionOfTheTree(t *testing.T) {
	for _, held := range []struct {
		files, want int
		why         string
	}{
		{files: 0, want: leastReadInOneGo, why: "a tree with nothing in it still splices what a judging differs by"},
		{files: 100, want: leastReadInOneGo, why: "a quarter of a small tree is a handful, and reading a handful is free"},
		{files: 1200, want: 300, why: "a quarter of the tree, which is three seconds of reading"},
		{files: 2800, want: 700, why: "a quarter of a middling tree, about six seconds"},
		{files: 5286, want: mostReadInOneGo, why: "a quarter of a consumer's tree is over ten seconds, which is " +
			"longer than every hook of a session may wait on one judging"},
		{files: 100_000, want: mostReadInOneGo, why: "a quarter of a huge tree would block every hook of the session"},
	} {
		if got := readsInOneGo(held.files); got != held.want {
			t.Errorf("a tree of %d files splices %d files for one judging, want %d: %s",
				held.files, got, held.want, held.why)
		}
	}

	if leastReadInOneGo > mostReadInOneGo {
		t.Errorf("the least a judging splices, %d, is more than the most, %d", leastReadInOneGo, mostReadInOneGo)
	}
}

func TestAWorkerIsServedFromTheTreeOfTheBranchItWasCutFrom(t *testing.T) {
	root, _ := workerProject(t)

	heldBaseOf(t, root)

	for _, command := range [][]string{{"checkout", "-q", "-b", "the-main-agents-own-work"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	answer, asked := warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: worktree})
	if !asked {
		t.Fatalf("a worker's judging was declined after the main checkout moved off the branch it was cut from: %s",
			answer.Because)
	}

	if !strings.Contains(answer.Out, "order.py") {
		t.Errorf("the served answer does not name the worker's own sin:\n%s%s", answer.Out, answer.Err)
	}
}

func TestEveryReasonAJudgingDeclinesForReadsAsASentence(t *testing.T) {
	root, _ := migrationProject(t)
	project := git.ProjectRoot(root)
	kept.Drop(project, git.CurrentBranch(project))

	settings, _ := config.Load(root)
	sources := scan.Walk([]string{root}, source.Under(root, settings.Excluded))

	for why, in := range map[string]string{
		"no tree is held":              root,
		"a folder outside the project": t.TempDir(),
	} {
		declined := ""
		if _, err := heldOrDeclined(root, in, &declined, new(int))(sources); err != nil {
			t.Fatalf("%s: %v", why, err)
		}

		if declined == "" {
			t.Errorf("%s: the judging declined with no reason at all", why)

			continue
		}

		if len(strings.Fields(declined)) < 4 {
			t.Errorf("%s: the reason is not a sentence: %q", why, declined)
		}
	}
}
