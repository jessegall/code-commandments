package workspace

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func TestAWorktreeOfAJournalProjectKeepsItsStateInTheProjectsPluginData(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	manifest := filepath.Join(project, ".journal", "plugins", JournalPlugin, ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	worktree := filepath.Join(project, ".journal", "worktrees", "story-engine-grace")
	for _, line := range [][]string{
		{"init", "-q"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "one"},
		{"worktree", "add", "-q", "-b", "grace", worktree},
	} {
		if out, err := exec.Command("git", append([]string{"-C", project}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	space := At(worktree, "")

	if !space.IsJournalDriven() {
		t.Error("a worktree of a project whose journal runs the plugin is not taken as journal driven")
	}

	if want := filepath.Join(project, ".journal", "plugin-data", JournalPlugin); space.StateDir() != want {
		t.Errorf("the worktree keeps its state in %s, not the project's plugin data %s", space.StateDir(), want)
	}
}
