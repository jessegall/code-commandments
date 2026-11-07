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

func TestTheReportedSinsAreTrackedWhileTheGeneratedStateIsNot(t *testing.T) {
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
		workspace.At(root, "").Shared(reported.Filename): true,
		workspace.At(root, "").Shared("config.json"):     false,
		workspace.At(root, "").Shared("cross-file.json"): false,
	} {
		if err := os.WriteFile(path, []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}

		out, _ := exec.Command("git", "-C", root, "check-ignore", path).CombinedOutput()
		if ignored := strings.TrimSpace(string(out)) != ""; ignored == tracked {
			name := filepath.Base(path)
			if tracked {
				t.Errorf("%s is ignored, so a report it holds reaches no clone, no teammate and no CI", name)

				continue
			}
			t.Errorf("%s is tracked, and it is state this tool regenerates", name)
		}
	}
}
