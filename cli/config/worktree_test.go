package config_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

// TestAWorktreeIsJudgedByTheProjectsConfig holds the settings a worker is judged by to the ones its project's
// owner set. A worktree is a place to do work and not a project of its own: read from the worktree, the settings
// are whatever its branch happens to carry — an older set of excluded folders, an older tuning of a rule, or,
// where the branch has no config at all, nothing. A worker was then judged by rules nobody chose, silently, with
// its findings reading exactly like everyone else's.
func TestAWorktreeIsJudgedByTheProjectsConfig(t *testing.T) {
	project := repository(t)

	write(t, filepath.Join(project, ".commandments", "config.json"), `{"paths": ["src"], "exclude": ["vendor"]}`)
	run(t, project, "add", "-A")
	run(t, project, "commit", "-m", "the project's own config")

	worktree := filepath.Join(t.TempDir(), "work")
	run(t, project, "worktree", "add", "-b", "a-worker", worktree)

	// The worktree's branch changes the config, as a branch may: its own excludes are not the project's.
	write(t, filepath.Join(worktree, ".commandments", "config.json"), `{"paths": ["src"], "exclude": ["everything-the-worker-wrote"]}`)

	read, err := config.Load(worktree)
	if err != nil {
		t.Fatal(err)
	}

	if slices.Contains(read.Excluded, "everything-the-worker-wrote") {
		t.Error("a worktree is judged by its own branch's config, so a worker can exclude itself from judgement")
	}

	if !slices.Contains(read.Excluded, "vendor") {
		t.Errorf("the project's own excludes do not reach the worktree: %v", read.Excluded)
	}
}

// TestAProjectOutsideGitIsItsOwnSettingsRoot holds the quiet case: a folder in no repository has no main
// checkout to look to, and reads its own config as it always did.
func TestAProjectOutsideGitIsItsOwnSettingsRoot(t *testing.T) {
	root := t.TempDir()
	write(t, filepath.Join(root, ".commandments", "config.json"), `{"paths": ["src"], "exclude": ["dist"]}`)

	read, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	if !slices.Contains(read.Excluded, "dist") {
		t.Errorf("a folder in no repository does not read its own config: %v", read.Excluded)
	}
}

// repository is a git repository with one commit, which a worktree can be cut from.
func repository(t *testing.T) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	run(t, root, "init", "-b", "main")
	run(t, root, "config", "user.email", "test@example.com")
	run(t, root, "config", "user.name", "A Test")
	write(t, filepath.Join(root, "src", "a.txt"), "a\n")
	run(t, root, "add", "-A")
	run(t, root, "commit", "-m", "first")

	return root
}

func run(t *testing.T, root string, args ...string) {
	t.Helper()

	command := exec.Command("git", args...)
	command.Dir = root
	if out, err := command.CombinedOutput(); err != nil {
		t.Fatalf("git %v: %v\n%s", args, err, out)
	}
}

func write(t *testing.T, path, contents string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(contents), 0o644); err != nil {
		t.Fatal(err)
	}
}
