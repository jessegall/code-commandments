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

	for path, text := range map[string]string{
		"config.json":       "{\"paths\": [\"src\"]}\n",
		"reports.json":      "{}\n",
		".gitignore":        "*\n!.gitignore\n!custom/\n!custom/**\n",
		"custom/Order.json": "{}\n",
	} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, ".commandments", path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, ".commandments", path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
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

	held := TheState(root)

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

	if held := TheState(root); held.Done || held.Said != "" {
		t.Errorf("a project with no committed config was told %q", held.Said)
	}
}

func TestAProjectThatIsNoRepositoryIsLeftAlone(t *testing.T) {
	if held := TheState(t.TempDir()); held.Done || held.Said != "" {
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

func journalDriven(t *testing.T, root string) {
	t.Helper()

	manifest := filepath.Join(root, ".journal", "plugins", workspace.JournalPlugin, ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(manifest, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}
}

func TestAWorktreeKeepsOnlyItsDetectorsAndNothingItHandsInDeletesTheRest(t *testing.T) {
	root := projectWithACommittedConfig(t)
	journalDriven(t, root)
	worktree := worktreeOf(t, root)

	if err := os.MkdirAll(filepath.Join(worktree, ".commandments", "sessions", "a1b2c"), 0o755); err != nil {
		t.Fatal(err)
	}

	said := EveryWorktree(root)

	for _, gone := range []string{"config.json", "reports.json", "sessions"} {
		if _, err := os.Stat(filepath.Join(worktree, ".commandments", gone)); !os.IsNotExist(err) {
			t.Errorf("the worktree still holds %s, so it is unclear where its config and state come from: %v", gone, err)
		}
	}

	if _, err := os.Lstat(filepath.Join(worktree, ".commandments", "custom", "Order.json")); err != nil {
		t.Errorf("the worktree lost its detectors: %v", err)
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Fatalf("the main checkout's own settings went with it: %v", err)
	}

	if status := gitIn(t, worktree, "status", "--porcelain"); status != "" {
		t.Errorf("git in the worktree sees the clean as a change, so a hand-in would commit it:\n%s", status)
	}

	if err := os.WriteFile(filepath.Join(worktree, ".commandments", "custom", "Paid.json"), []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(worktree, "work.txt"), []byte("the worker's work\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	gitIn(t, worktree, "add", "-A")
	gitIn(t, worktree, "commit", "-qm", "the worker's work")

	changed := gitIn(t, worktree, "show", "--name-status", "--format=", "HEAD")
	if strings.Contains(changed, "config.json") || strings.Contains(changed, "reports.json") {
		t.Errorf("the worker's commit carries the cleaned state:\n%s", changed)
	}

	if !strings.Contains(changed, ".commandments/custom/Paid.json") {
		t.Errorf("a detector the worker wrote is not committed with its work:\n%s", changed)
	}

	if !strings.Contains(strings.Join(said, "\n"), worktree) {
		t.Errorf("nothing says which worktree was cleaned: %q", said)
	}
}

func TestStateWrittenIntoAWorktreeLaterIsCleanedOnTheNextPass(t *testing.T) {
	root := projectWithACommittedConfig(t)
	journalDriven(t, root)
	worktree := worktreeOf(t, root)

	EveryWorktree(root)

	if said := EveryWorktree(root); len(said) != 0 {
		t.Errorf("a worktree already clean is reported again: %q", said)
	}

	if err := os.WriteFile(filepath.Join(worktree, ".commandments", "config.json"), []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	EveryWorktree(root)

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); !os.IsNotExist(err) {
		t.Errorf("a config written into the worktree after it was cut is still there: %v", err)
	}
}

func TestTheSparsePatternsAnOldReleaseWroteAreTurnedOff(t *testing.T) {
	root := projectWithACommittedConfig(t)
	worktree := worktreeOf(t, root)

	gitIn(t, worktree, "sparse-checkout", "set", "--no-cone", "/*", "!/.commandments/config.json")

	EveryWorktree(root)

	if said := gitIn(t, worktree, "config", "--get", "core.sparseCheckout"); strings.TrimSpace(said) == "true" {
		t.Error("the worktree still leaves files out of its checkout")
	}

	if status := gitIn(t, worktree, "status", "--porcelain"); status != "" {
		t.Errorf("git in the worktree sees a change:\n%s", status)
	}
}

func TestAJournalProjectCommitsTheUntrackingAndKeepsItsIndexClean(t *testing.T) {
	root := projectWithACommittedConfig(t)
	journalDriven(t, root)

	if held := TheState(root); !held.Done {
		t.Fatalf("a journal project's committed state was not untracked: %q", held.Said)
	}

	for _, path := range []string{".commandments/config.json", ".commandments/reports.json"} {
		if git.Tracks(root, path) {
			t.Errorf("%s is still tracked, so every worktree carries a copy", path)
		}
	}

	if !git.Tracks(root, ".commandments/custom/Order.json") || !git.Tracks(root, ".commandments/.gitignore") {
		t.Error("the detectors or the folder's .gitignore are no longer tracked")
	}

	if staged := gitIn(t, root, "status", "--porcelain", "--untracked-files=no"); staged != "" {
		t.Errorf("the main checkout of a project the journal drives was left with a change, and the journal "+
			"merges every worker into that checkout, so each merge is refused:\n%s", staged)
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Errorf("the project's config left the main checkout's disk: %v", err)
	}
}

func TestASyncInsideAWorktreeNeverCleansTheMainCheckout(t *testing.T) {
	root := projectWithACommittedConfig(t)
	journalDriven(t, root)
	worktree := worktreeOf(t, root)

	EveryWorktree(worktree)

	for _, path := range []string{"config.json", "reports.json"} {
		if _, err := os.Stat(filepath.Join(root, ".commandments", path)); err != nil {
			t.Errorf("a run from inside a worktree took %s off the main checkout: %v", path, err)
		}
	}

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); !os.IsNotExist(err) {
		t.Errorf("the worktree the run started in was not cleaned: %v", err)
	}

	if cleaned, _ := Clean(root); cleaned {
		t.Error("the main checkout was cleaned as if it were a worktree")
	}
}

func TestAProjectTheJournalDoesNotDriveKeepsItsWorktreesAsTheyAre(t *testing.T) {
	root := projectWithACommittedConfig(t)
	worktree := worktreeOf(t, root)

	EveryWorktree(root)

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); err != nil {
		t.Errorf("a worktree the user made by hand lost its files: %v", err)
	}
}

func TestALinkedCommandmentsFolderIsNeverFollowed(t *testing.T) {
	root := projectWithACommittedConfig(t)
	journalDriven(t, root)
	worktree := worktreeOf(t, root)

	elsewhere := t.TempDir()
	if err := os.WriteFile(filepath.Join(elsewhere, "config.json"), []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if err := os.RemoveAll(filepath.Join(worktree, ".commandments")); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink(elsewhere, filepath.Join(worktree, ".commandments")); err != nil {
		t.Fatal(err)
	}

	Clean(worktree)

	if _, err := os.Stat(filepath.Join(elsewhere, "config.json")); err != nil {
		t.Errorf("a clean followed the link and deleted the folder it points at: %v", err)
	}
}
