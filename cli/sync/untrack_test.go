package sync

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
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

	held := UntrackTheConfig(root)

	if !held.Done {
		t.Fatalf("a committed config was not untracked: %q", held.Said)
	}

	if tracked(root, ".commandments/config.json") {
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

	if held := UntrackTheConfig(root); held.Done || held.Said != "" {
		t.Errorf("a project with no committed config was told %q", held.Said)
	}
}

func TestAProjectThatIsNoRepositoryIsLeftAlone(t *testing.T) {
	if held := UntrackTheConfig(t.TempDir()); held.Done || held.Said != "" {
		t.Errorf("a folder that is no git repository was told %q", held.Said)
	}
}
