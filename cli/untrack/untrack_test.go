package untrack

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func projectWithACommittedConfig(t *testing.T) string {
	t.Helper()

	root := t.TempDir()

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if err := os.MkdirAll(filepath.Join(root, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(filepath.Join(root, ".commandments", "config.json"),
		[]byte("{\"paths\": [\"src\"]}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"add", "-f", "-A"}, {"commit", "-qm", "with its config"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	return root
}

func TestACommittedConfigIsUntrackedAndLeftOnDisk(t *testing.T) {
	root := projectWithACommittedConfig(t)

	held := TheConfig(root)

	if !held.Done {
		t.Fatalf("a committed config was not untracked: %q", held.Said)
	}

	if git.Tracks(root, ".commandments/config.json") {
		t.Error("git still follows the config, so every worktree of this project carries a copy of it")
	}

	read, err := os.ReadFile(filepath.Join(root, ".commandments", "config.json"))
	if err != nil {
		t.Fatalf("the project's own settings were deleted: %v", err)
	}

	if !strings.Contains(string(read), "src") {
		t.Errorf("the project's own settings were rewritten: %s", read)
	}

	if !strings.Contains(held.Said, "no longer tracked") {
		t.Errorf("nothing says what happened: %q", held.Said)
	}
}

func TestAConfigThatWasNeverTrackedIsLeftAlone(t *testing.T) {
	root := t.TempDir()

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if held := TheConfig(root); held.Done || held.Said != "" {
		t.Errorf("a project with no committed config was told %q", held.Said)
	}
}

func TestAProjectThatIsNoRepositoryIsLeftAlone(t *testing.T) {
	if held := TheConfig(t.TempDir()); held.Done || held.Said != "" {
		t.Errorf("a folder that is no git repository was told %q", held.Said)
	}
}

func worktreeOf(t *testing.T, root string) string {
	t.Helper()

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "a-worker", worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); err != nil {
		t.Fatalf("the worktree never carried the config, so this proves nothing: %v", err)
	}

	return worktree
}

func gitIn(t *testing.T, dir string, args ...string) string {
	t.Helper()

	out, err := exec.Command("git", append([]string{"-C", dir, "-c", "user.email=t@t", "-c", "user.name=t"}, args...)...).CombinedOutput()
	if err != nil {
		t.Fatalf("git %v: %s", args, out)
	}

	return string(out)
}

func TestAnOpenWorktreeLosesItsCopyOfTheConfigAndNothingItHandsInDeletesIt(t *testing.T) {
	root := projectWithACommittedConfig(t)
	worktree := worktreeOf(t, root)

	said := Everywhere(root)

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); !os.IsNotExist(err) {
		t.Errorf("the worktree still has its copy of the config on disk, and reads it instead of the journal's "+
			"settings: %v", err)
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Fatalf("the main checkout's own settings went with it: %v", err)
	}

	if status := gitIn(t, worktree, "status", "--porcelain"); strings.Contains(status, "config.json") {
		t.Errorf("git in the worktree sees the config as changed, so a hand-in would commit its deletion and "+
			"the merge would delete it on the main branch:\n%s", status)
	}

	if err := os.WriteFile(filepath.Join(worktree, "work.txt"), []byte("the worker's work\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	gitIn(t, worktree, "add", "-A")
	gitIn(t, worktree, "commit", "-qm", "the worker's work")

	if changed := gitIn(t, worktree, "show", "--name-status", "--format=", "HEAD"); strings.Contains(changed, "config.json") {
		t.Errorf("the worker's commit carries the config:\n%s", changed)
	}

	if !strings.Contains(strings.Join(said, "\n"), worktree) {
		t.Errorf("nothing says which worktree lost its copy: %q", said)
	}
}

func TestASecondRunOverTheSameWorktreesSaysNothing(t *testing.T) {
	root := projectWithACommittedConfig(t)
	worktreeOf(t, root)

	Everywhere(root)

	if said := OutOfEveryWorktree(root); len(said) != 0 {
		t.Errorf("a worktree already cleaned is reported again on the next run, so every install and every "+
			"settings change repeats it: %q", said)
	}
}

func TestAWorktreeThatAlreadyLeavesFilesOutKeepsItsConfig(t *testing.T) {
	root := projectWithACommittedConfig(t)
	worktree := worktreeOf(t, root)

	gitIn(t, worktree, "sparse-checkout", "set", "--no-cone", "/*", "!/docs/")

	said := OutOfEveryWorktree(root)

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); err != nil {
		t.Errorf("a worktree whose checkout somebody already shaped had its patterns written over: %v", err)
	}

	if !strings.Contains(strings.Join(said, "\n"), "somebody's choice") {
		t.Errorf("nothing says why that worktree still has its copy: %q", said)
	}
}

func TestAJournalProjectsMainCheckoutIndexIsNeverTouched(t *testing.T) {
	root := projectWithACommittedConfig(t)
	worktree := worktreeOf(t, root)

	manifest := filepath.Join(root, ".journal", "plugins", workspace.JournalPlugin, ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(manifest, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}

	Everywhere(root)

	if staged := gitIn(t, root, "status", "--porcelain", "--", ".commandments"); staged != "" {
		t.Errorf("the main checkout of a project the journal drives was left with a change in its index, and the "+
			"journal merges every worker into that checkout, so each merge is refused:\n%s", staged)
	}

	if !git.Tracks(root, ".commandments/config.json") {
		t.Error("the config a journal project tracks on purpose is no longer tracked")
	}

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); !os.IsNotExist(err) {
		t.Errorf("the worktree's copy is still on its disk: %v", err)
	}
}
