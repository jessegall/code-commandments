package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func TestAMachineWeCannotWriteOutsideOurOwnFolderStillAnswers(t *testing.T) {
	forgetBookkeeping(t)

	root, _ := workerProject(t)

	refused := filepath.Join(t.TempDir(), "refused")
	if err := os.MkdirAll(refused, 0o500); err != nil {
		t.Fatal(err)
	}

	t.Setenv(pluginData, filepath.Join(refused, "plugin-data"))

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	said := Answer4Of(map[string]any{
		"v": 1, "event": "file_edited", "agent": 1, "cwd": root, "role": "main",
		"data": map[string]any{"cwd": root, "kind": "create", "path": under},
	})

	if said == "" {
		t.Fatal("a machine whose plugin folder we cannot write answered nothing at all")
	}

	if told := BookkeepingSaid(); told != "" && !strings.Contains(told, "cannot write") {
		t.Errorf("it does not say why its bookkeeping went elsewhere: %s", told)
	}
}

func TestAMachineWithNoGitRefusesInWordsRatherThanGuessing(t *testing.T) {
	root := t.TempDir()

	if checkout := checkoutOf(root, filepath.Join(root, "src", "made.py")); checkout != root {
		t.Errorf("with no git repository at all the checkout is answered as %q, and the only honest answer is the root we were given", checkout)
	}

	if cut := cutOf(NewEvent(map[string]any{"hook_event_name": "PostToolUse", "cwd": root}, root)); cut != "" {
		t.Errorf("with no git the commit a checkout was cut from is answered as %q", cut)
	}

	if named := git.ChangedBetween(root, "aaaaaaa", "bbbbbbb"); len(named) != 0 {
		t.Errorf("with no git a commit range names %d file(s)", len(named))
	}
}
