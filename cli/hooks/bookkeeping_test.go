package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func forgetBookkeeping(t *testing.T) {
	t.Helper()

	bookkeeping.Lock()
	bookkeeping.decided, bookkeeping.folder, bookkeeping.instead = false, "", ""
	bookkeeping.Unlock()

	t.Cleanup(func() {
		bookkeeping.Lock()
		bookkeeping.decided, bookkeeping.folder, bookkeeping.instead = false, "", ""
		bookkeeping.Unlock()
	})
}

func TestACommandRunOutsideThePluginReadsTheBookkeepingOfTheJournal(t *testing.T) {
	forgetBookkeeping(t)

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	manifest := filepath.Join(root, ".journal", "plugins", workspace.JournalPlugin, ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	t.Setenv(pluginData, "")

	if folder, want := bookkeepingIn(root), filepath.Join(root, ".journal", "plugin-data", workspace.JournalPlugin); folder != want {
		t.Errorf("a command with no plugin folder looks in %s, and the journal's judgings are recorded in %s", folder, want)
	}
}

func TestTheBookkeepingGoesWhereTheJournalSaidWhenItCanBeWritten(t *testing.T) {
	forgetBookkeeping(t)

	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	if folder := bookkeepingIn(root); folder != data {
		t.Errorf("the bookkeeping goes to %s, and the journal gave us %s", folder, data)
	}

	if said := BookkeepingSaid(); said != "" {
		t.Errorf("it said something about a folder it could write: %s", said)
	}
}

func TestAFolderItCannotWriteIsSaidAndNothingStopsBeingWritten(t *testing.T) {
	forgetBookkeeping(t)

	root, _ := workerProject(t)

	refused := filepath.Join(t.TempDir(), "refused")
	if err := os.MkdirAll(refused, 0o500); err != nil {
		t.Fatal(err)
	}

	t.Setenv(pluginData, filepath.Join(refused, "plugin-data", "code-commandments"))

	folder := bookkeepingIn(root)

	if folder == os.Getenv(pluginData) {
		t.Skip("this filesystem lets anything be written, so a refused folder cannot be tried here")
	}

	Record(folder, Judged{Event: "PostToolUse", Kind: "checkout", Checkout: root, Abandoned: "a test"})

	said, err := os.ReadFile(filepath.Join(folder, "judgings.log"))
	if err != nil {
		t.Fatalf("the record went nowhere at all: %v", err)
	}

	if !strings.Contains(string(said), "a test") {
		t.Errorf("the record does not hold what was written: %s", said)
	}

	told := BookkeepingSaid()
	if !strings.Contains(told, folder) || !strings.Contains(told, "cannot write") {
		t.Errorf("it does not say where its bookkeeping went and why: %q", told)
	}
}
