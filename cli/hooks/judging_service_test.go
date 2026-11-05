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

// TestAJudgingAskedForOnTheSocketIsAnsweredFromTheTreeTheServiceHolds holds the command's half of the warm tree.
// A terminal run is handed no socket and no data folder, only the folder it was started in, so the socket is
// named from the project's path as a language bridge's is: the run computes the same name, asks, and the service
// judges against the tree it already holds instead of the run reading 1518 or 5286 files for itself.
//
// The sin it names is one only a cross-file read can find, so the answer also says the judging was done against
// the program and not against one file.
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

	// The console names the rule and the place, which is what a reader of a judging sees.
	for _, said := range []string{"MigrationByItsParentsDetector", "m004_drop_fades.py:4"} {
		if !strings.Contains(answer.Out, said) {
			t.Errorf("the answer does not name %s:\n%s%s", said, answer.Out, answer.Err)
		}
	}

	if answer.Code != 1 {
		t.Errorf("the answer exits %d, want 1 for a run that found a sin", answer.Code)
	}
}

// TestARunFindsNoServiceWhereNoneListens holds the fallback: nothing listening is no answer, and the run reads
// the tree itself as every run did before one was held.
func TestARunFindsNoServiceWhereNoneListens(t *testing.T) {
	root, _ := migrationProject(t)

	if _, asked := warm.Asked(root, warm.Request{Judge: []string{root}, In: root}); asked {
		t.Error("a run was answered by a service nobody started")
	}
}

// TestTheAnswerSaysHowLongThePluginTookOverTheEvent holds the two durations the journal shows on the sins card:
// what the judging itself took, and how long the plugin held the event altogether. They differ by the wait an
// event spends for its turn, since one event is judged at a time, and a reader given only the total cannot tell
// a slow judging from a queued one. Neither counts the time before the event, which is the journal's own.
func TestTheAnswerSaysHowLongThePluginTookOverTheEvent(t *testing.T) {
	root, data := migrationProject(t)

	// An answer is what carries the duration, so the base is held and the sin found: with nothing to say the
	// plugin answers nothing at all, which is the point of saying it only beside an answer.
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

	// The total is the judging plus whatever the event waited for its turn, so it is never the smaller of the
	// two: a reader comparing them is being told that wait.
	whole, told := queued[0]["judged_total_ms"].(float64)
	if !told || whole < took {
		t.Errorf("the answer says judged_total_ms %v against a judging of %v, told %v", whole, took, told)
	}

	if whole > float64(10*time.Minute/time.Millisecond) {
		t.Errorf("the answer says it held the event %v ms, which is no duration of one", whole)
	}

	// The reading's share is a part of the judging, never more than it: it is how much of that work was a
	// bridge parsing files rather than a rule reading the tree.
	read, shared := queued[0]["read_ms"].(float64)
	if shared && read > took {
		t.Errorf("the answer says read_ms %v of a judging of %v", read, took)
	}
}

// TestAJudgingTheBaseCannotCoverIsDeclined holds what the service must not do: read a tree inside the one
// process that answers every hook of the session. A worker ran a judging of its own worktree, whose files no base
// holds, and two later edits of its own waited two minutes behind that read. Declined, the run reads them in its
// own process, where it blocks nobody.
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

// TestAWorkersHandInJudgingIsAnsweredFromTheBase holds the saving for the judging a worker runs on its way out.
// A worktree's files are not the base's, so asking the base whether it holds them answered no every time and
// every hand-in read the whole tree in its own process, 17 to 55 seconds, and started a language bridge of its
// own beside the warm one: that second bridge is what still cost an edit 6 to 12 seconds of parsing while three
// hand-ins ran on a consumer's tree. Answered from the base, a hand-in reads the files its worktree differs by
// and nothing else.
//
// It must still FIND the worker's sin: answering "no sins" from a tree that holds the project's copy of the file
// instead of the worker's would be the worst of the three answers, a green tick over everything the worker wrote.
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

	// The run is the one a worker makes on its way out: started in its own worktree, with no path of its own,
	// so the folders come from the project's configuration and the root they join to is the worktree's.
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

// TestWhatTheShellDidToTheTreeIsJudged holds the tree against what happens to it outside the plugin's sight. An
// agent does not only edit: it removes a file with rm, writes one with a heredoc, renames one, checks a branch
// out. None of that raises an event, so a tree held in memory learns of it only by looking — and a tree that
// does not look answers about code that is not there and says nothing about code that is.
//
// Each of the three was a real wrong answer. A consumer's judging reported three sins in a file deleted a minute
// earlier. A file created with no event of its own was judged as though it did not exist, which is the quietest
// wrong answer there is. And a rename is both at once.
func TestWhatTheShellDidToTheTreeIsJudged(t *testing.T) {
	root, _ := migrationProject(t)
	heldBaseOf(t, root)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	// It is found at all to begin with, so what follows is about the tree and not about the rule.
	if said := judgingOf(t, root); !strings.Contains(said, "m004_drop_fades.py") {
		t.Fatalf("the sin is not found before anything is done to the tree:\n%s", said)
	}

	// Removed by the shell, as rm removes it: no event, nothing announced.
	gone := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")
	if err := os.Remove(gone); err != nil {
		t.Fatal(err)
	}

	if said := judgingOf(t, root); strings.Contains(said, "m004_drop_fades.py") {
		t.Errorf("a file the shell removed is still judged, from a tree held in memory:\n%s", said)
	}

	// Written by the shell, as a heredoc writes it: again no event.
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

	// And renamed, which is both at once: gone from where it was, there where it now is.
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

// heldBaseOf reads the project's tree and holds it as the base, as the first hook of a session has it held.
// Over the project's DECLARED ROOTS, which is what a session really reads, and never the whole project folder. A
// base read over the folder holds a worktree's own files directly, at the path they have under the project, so
// the mapping from a checkout's file to the project's own is never asked and a test over it proves nothing. A
// real base holds only the declared paths, and then that mapping is the whole of whether a worker is served.
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

// judgingOf is what a full judging of the project says, answered by the service from the tree it holds.
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

// TestAFileAWorkerDeletedIsNotInItsProgram holds the other half of a worktree standing in for the project. A
// worker's file stands IN FOR the project's own at the same path, so a file the worker deleted must leave the
// program with it: left in, the project's copy takes the place of a module the worker removed, and every rule
// that follows an import, a parent or a caller answers about code the worker no longer has.
//
// git is asked for the worktree's changes with deletions EXCLUDED, since a deleted file is nothing to read, so
// they are asked for separately and joined to what the view leaves out.
func TestAFileAWorkerDeletedIsNotInItsProgram(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	own := filepath.Join(worktree, "src", "order.py")
	stands := filepath.Join(root, "src", "order.py")

	// While it is there, the worker's own copy is what the program holds, and the project's is not.
	reading := heldOrDeclined(root, worktree, new(string))

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

	// Deleted by the worker, and committed, which is how a worker removes a file.
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

// TestADeclineSaysWhichOfTheThreeMadeTheFilesDiffer holds the decline's own words to the one thing that makes
// them worth anything: what to do about them. A judging reads the files it differs from the tree by, and three
// quite different things put a file in that list — the tree's own files whose bytes have moved since it read
// them, the files the run's walk found that the tree never read at all, and the files a worktree differs from the
// tree's branch by. Each has a different fix and a total tells which of them nothing.
//
// A consumer measured 1206 differing files for a worktree whose content differs by 79, and two sessions spent an
// hour guessing between a stale tree, a tree read over narrower paths than the run walks, and the worker's own
// work. The number alone could not settle it, and the paths would have in one run.
func TestADeclineSaysWhichOfTheThreeMadeTheFilesDiffer(t *testing.T) {
	differs := Differs{
		Moved:  []string{"/p/src/a.py"},
		Unread: []string{"/p/.agents/skills/x.py", "/p/.agents/skills/y.py", "/p/.agents/skills/z.py"},
		Own:    []string{"/p/src/b.py", "/p/src/c.py"},
		Asked:  map[string]string{"/p/.agents/skills/x.py": "/project/.agents/skills/x.py"},
		Tree:   "the tree holds [python] and was read over [/project/src]",
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

	// And the paths of the LARGEST, since what those files have in common is the whole answer: here that every
	// one of them lies under a folder the tree was never read over.
	for _, path := range differs.Unread {
		if !strings.Contains(said, path) {
			t.Errorf("the decline names no path of the group it blames:\n%s", said)

			break
		}
	}

	// Each file said once, however many of the three put it there: a file a worktree changed is also a file the
	// tree's bytes no longer match, and counting it twice would decline a judging the service could serve.
	twice := Differs{Moved: []string{"/p/src/a.py"}, Own: []string{"/p/src/a.py"}}
	if read := twice.read(); len(read) != 1 {
		t.Errorf("one file in two of the three groups reads as %v", read)
	}

	// A group with nothing in it says so rather than naming a path it does not have.
	if said := (Differs{}).where(); !strings.Contains(said, "none to name") {
		t.Errorf("a judging that differs by nothing says %q", said)
	}

	// The path the tree was really ASKED about, beside the file, and what the tree itself is. Without those two a
	// count is unanswerable from outside: three tags were spent guessing at a consumer's four-figure count, and
	// each guess was a release.
	if !strings.Contains(said, "asked about /project/.agents/skills/x.py") {
		t.Errorf("the decline does not say which path the tree was asked about:\n%s", said)
	}

	if !strings.Contains(said, "was read over [/project/src]") {
		t.Errorf("the decline does not say what the tree itself holds:\n%s", said)
	}
}

// TestAWorkersChangesAreJudgedWhereTheServiceAnswersIt holds a worker's `judge --changes` through the service,
// which is the one shape the scope tests never cover: they run the command in the worktree with nothing warm, and
// the service runs it in a process whose own folder is the PLUGIN's, on behalf of a run standing somewhere else.
// A scope worked out from the wrong folder answers that nothing changed, and that answer is a green tick over
// every line the worker wrote.
//
// Reported from a consumer on v7.15.4: a worker's `judge --changes` said "Nothing to judge" while its checkout
// held uncommitted edits, minutes after a run in the same checkout judged 22 files.
func TestAWorkersChangesAreJudgedWhereTheServiceAnswersIt(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	// The worktree's own uncommitted edit, which workerProject left there: committed in the project, rewritten
	// here, so `git status` in this checkout is the only thing that knows about it.
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	// And MANY of them, since one is exactly the convenient difference that hides a bug: a consumer's worker
	// with hundreds of uncommitted files was served and answered "no source file under the path is among the
	// lines changed", which reads as a clean pass over every one of them.
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

	// And every one of the many, not merely the first: a scope that keeps a handful and drops the rest answers a
	// clean pass over the rest, and nothing says so.
	for _, named := range []string{"part000.py", "part100.py", fmt.Sprintf("part%03d.py", leastReadInOneGo-51)} {
		if !strings.Contains(answer.Out, named) {
			t.Errorf("%s was changed in the worktree and is not in the answer:\n%s%s", named, answer.Out, answer.Err)
		}
	}
}

// TestAPathInsideAWorktreeIsTheOnlyThingJudged holds a path argument against the tree the service answers from.
// The base is the PROJECT's, rooted at the project, and a worktree's files stand in for the project's own at the
// same path under it. A path the run names is a path in the WORKTREE, and if what is judged is worked out against
// the project's root instead, the answer is about the project's copies: every finding in it lies in a file the
// run was never asked about, and a reader takes them for its own.
//
// Reported from a consumer on v7.15.4: a judge given an absolute path inside a worker's worktree reported 204
// findings in the main checkout's source, none of them the worker's.
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

	// A sin in the PROJECT's own copy of a file, which the named path does not cover: it lies in the project's
	// src, not the worktree's, so nothing about this run may report it.
	elsewhere := filepath.Join(root, "src", "invoice.py")
	if err := os.WriteFile(elsewhere, []byte("class Invoice:\n    def total(self):\n        \"\"\"Says the total.\"\"\"\n        return 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	// The path the run names, absolute, inside the worktree, holding the worker's own sin.
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

	// And narrower still: ONE file of the worktree named, with a sin in a sibling of its own checkout. A path
	// that only chose what to report, while the run judged everything it read, answers with both — which is the
	// 204 findings outside the task, in the worker's own tree rather than the project's.
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

// TestAWorktreesFilesAreNotAllFilesTheTreeNeverRead holds the mapping a worktree's judging turns on. A worktree's
// file at <worktree>/src/order.py stands in for the project's own <project>/src/order.py, and the base is asked
// about the PROJECT's path. Asked about the worktree's instead, the base knows none of them: every file of the
// checkout counts as a file the tree never read, the count runs past what one judging reads, and the run is
// declined and reads the whole tree in its own process.
//
// Measured on a consumer on v7.15.7, in a worker whose branch is 127 files from the tip: 1201 files "the walk
// found that the tree never read", every one of them an ordinary source file inside the worktree.
//
// Asked of differingFrom itself, because the whole-judging tests cannot see it: their worktrees hold two files,
// well under what one judging reads, so a hand-in is served whether the mapping works or not.
func TestAWorktreesFilesAreNotAllFilesTheTreeNeverRead(t *testing.T) {
	root, _ := workerProject(t)
	heldBaseOf(t, root)

	named, base, held := baseFor(root)
	if !held {
		t.Fatal("no tree is held, so there is nothing to ask")
	}

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	// Every python file of the worktree, which is what a judging in it walks. Each one stands in for the
	// project's own copy at the same path under it, and the base read those.
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

// TestAWorkerIsServedInTheShapeTheJournalReallyLaysOut mimics a consumer's own layout rather than a convenient
// one, because two tags were spent guessing at it. What is really there: declared paths NESTED two deep
// (src/core, src/viewer, tools/journal-hook), a worktree the journal keeps TWO folders down inside the project
// (.journal/worktrees/<environment>/worker-N), on a branch of its own, and a base read over those declared paths
// by the session that keeps it up rather than over the project folder.
//
// Each of those alone is harmless. Together they are what a worker's judging actually meets, and on a consumer
// 1207 of its files came back as files the tree never read — four figures for a branch 223 files from the tip —
// so every hand-in was declined and read 5913 files in its own process.
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

		// OUTSIDE every declared path, and a language the project judges. The tree is read over the declared
		// paths while a run walks the checkout it stands in, so these are files the tree was never going to
		// hold — the third and last thing a file counted as "never read" can be, after a counterpart built wrong
		// and a language the tree holds nothing of.
		"scripts/release.py":         "class Release:\n    pass\n",
		"docs/examples/sample.py":    "class Sample:\n    pass\n",
		"src/sandbox/throwaway.py":   "class Throwaway:\n    pass\n",
		// A language the TREE does not read: the C# bridge compiles whole units of its own and is kept warm as a
		// service, so a base never holds one. The walk still finds the file, and asking the tree about it answers
		// no every time — which is a file counted as changed for ever, in a count that decides whether a worker
		// is served at all.
		"src/core/Order.cs":  "namespace Shop;\n\npublic class Order {}\n",
		"src/core/Refund.cs": "namespace Shop;\n\npublic class Refund {}\n",
	} {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	// The worktree where the journal really keeps one: under an environment's own folder, two below .journal.
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

	// The worker's own sin, in its own checkout, under one of the nested declared paths.
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

	// Every file of the worktree the run walks, which is what it asks the tree about.
	var asked []string
	if err := filepath.WalkDir(worktree, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || strings.Contains(path, "/.git/") {
			return err
		}
		// Every language the run walks, which is what it really asks the tree about, and not the one language
		// this fixture's rule happens to judge.
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

	// The files outside every declared path are the ONLY ones the tree can honestly call new to it, and they
	// must be exactly those: anything else counted means the mapping or the languages are wrong again.
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

	// And each of them IS counted, so the check above is not passing for the want of anything to look at: a file
	// the tree was never read over is honestly new to it, and reading it is what the splice is for.
	for file := range outside {
		if !counted[file] {
			t.Errorf("%s lies outside every declared path, so the tree never read it, yet nothing counts it: the "+
				"judging would be answered as though that file did not exist", file)
		}
	}

	// And the judging itself is served, which is the whole point of the mapping.
	answer, asking := warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: worktree})
	if !asking {
		t.Fatalf("a worker's hand-in was declined: %s", answer.Because)
	}

	if !strings.Contains(answer.Out, "order.py") {
		t.Errorf("the served answer does not name the worker's own sin:\n%s%s", answer.Out, answer.Err)
	}
}

// TestHowManyFilesAreReadInOneGoIsAFractionOfTheTree holds the number that decides whether a judging is served
// at all, against the two costs it stands between. Reading the files that differ costs one bridge ask, in the
// process every hook of the session is answered by; declining costs the RUN a whole-tree read of 25 to 55 seconds
// in a process of its own, plus a second language bridge beside the warm one.
//
// Measured on a 1000-file tree (TestWhatAnAskCostsByItsFiles): one file 1.03s, 200 files 2.48s, 400 files 3.81s,
// 800 files 6.86s. So the ask is about a second plus seven milliseconds a file, and serving wins by a wide margin
// at every count that fits in memory. What it cannot win is a change the size of the tree: past that the base is
// half a tree out of date and reading it here buys nothing.
//
// So the number is a FRACTION of the tree, not a constant: a constant is either too small for a big tree, which
// declined a consumer's every worker hand-in, or too large for a small one, where a quarter is a handful.
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

	// And the floor is never above the ceiling, whatever the two are set to.
	if leastReadInOneGo > mostReadInOneGo {
		t.Errorf("the least a judging splices, %d, is more than the most, %d", leastReadInOneGo, mostReadInOneGo)
	}
}

// TestAWorkerIsServedFromTheTreeOfTheBranchItWasCutFrom holds the tree a worker answers from. A worker stands on
// a branch of its own, cut from the branch the main agent works on — development, version-5, whatever it is
// called — and a branch per worker can never have a tree under its own name. So the tree it answers from is the
// one the project holds for the branch its own branch is BASED ON, not the one named by whatever the main
// checkout happens to be standing on at that moment.
//
// Keyed and looked up by the main checkout's current branch, the lookup found nothing the moment that checkout
// moved: measured on a consumer at 127 seconds of reading for six changed files, "no tree is held for this
// project on its branch yet", on every hand-in, all afternoon.
func TestAWorkerIsServedFromTheTreeOfTheBranchItWasCutFrom(t *testing.T) {
	root, _ := workerProject(t)

	// The tree as the main agent's session has it read, while that checkout stands on the branch the worker was
	// cut from.
	heldBaseOf(t, root)

	// And then the main checkout moves, as a main agent does: a new branch for its own work. Nothing about the
	// worker changed, and its tree must not vanish because somebody else switched.
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

	// And it is the worker's OWN sin, not the project's copy of that file: the tree answers about its program
	// with the worktree's own files standing in.
	if !strings.Contains(answer.Out, "order.py") {
		t.Errorf("the served answer does not name the worker's own sin:\n%s%s", answer.Out, answer.Err)
	}
}
