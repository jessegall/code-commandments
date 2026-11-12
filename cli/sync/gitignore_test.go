package sync

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestOnlyTheDetectorsAreTrackedAndEveryOtherFileIsState(t *testing.T) {
	root := t.TempDir()
	if err := os.MkdirAll(workspace.At(root, "").Dir(), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := ensureCommandmentsGitignore(root); err != nil {
		t.Fatal(err)
	}

	if out, err := exec.Command("git", "-C", root, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	for path, tracked := range map[string]bool{
		workspace.At(root, "").Shared("custom/Order.json"):         true,
		workspace.At(root, "").Shared("custom/skill/SKILL.md"):     true,
		workspace.At(root, "").Shared(reported.Filename):           false,
		workspace.At(root, "").Shared("config.json"):               false,
		workspace.At(root, "").Shared("sessions/a1b2c/tasks/1.md"): false,
		workspace.At(root, "").Shared("cross-file.json"):           false,
	} {
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}

		out, _ := exec.Command("git", "-C", root, "check-ignore", path).CombinedOutput()
		if ignored := strings.TrimSpace(string(out)) != ""; ignored == tracked {
			name := filepath.Base(path)
			if tracked {
				t.Errorf("%s is ignored, so a detector the project wrote reaches no clone, no worker and no merge", name)

				continue
			}
			t.Errorf("%s is tracked, and it is state read at the project root, so a checkout must carry no copy", name)
		}
	}
}

func TestASyncStripsTheOldTrackedLinesKeepsTheProjectsOwnAndTheDetectorsWin(t *testing.T) {
	root := t.TempDir()
	path := workspace.At(root, "").Shared(".gitignore")
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}

	old := "# code-commandments generated state; the lines below stay tracked.\n*\n!.gitignore\n!reports.json\n" +
		"!custom/\n!custom/**\n\n# A session's TASKS are tracked; everything else in its folder is this run's own state.\n" +
		"!sessions/\n!sessions/*/\n!sessions/*/tasks/\n!sessions/*/tasks/**\n!config.php\n!config.json\nmy-rule\ncustom/\n"
	if err := os.WriteFile(path, []byte(old), 0o644); err != nil {
		t.Fatal(err)
	}

	if err := ensureCommandmentsGitignore(root); err != nil {
		t.Fatal(err)
	}

	if out, err := exec.Command("git", "-C", root, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	written, _ := os.ReadFile(path)
	if !strings.Contains(string(written), "\nmy-rule\n") {
		t.Errorf("the project's own line was dropped:\n%s", written)
	}

	for file, tracked := range map[string]bool{
		"custom/Order.json":         true,
		"config.json":               false,
		"reports.json":              false,
		"sessions/a1b2c/tasks/1.md": false,
	} {
		whole := workspace.At(root, "").Shared(file)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}

		out, _ := exec.Command("git", "-C", root, "check-ignore", whole).CombinedOutput()
		if ignored := strings.TrimSpace(string(out)) != ""; ignored == tracked {
			t.Errorf("%s: ignored=%v after a sync of an old file:\n%s", file, ignored, written)
		}
	}
}
