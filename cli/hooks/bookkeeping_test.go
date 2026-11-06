package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
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
