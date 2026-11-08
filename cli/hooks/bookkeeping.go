package hooks

import (
	"os"
	"path/filepath"
	"sync"

	"github.com/jessegall/code-commandments/cli/workspace"
)

var bookkeeping struct {
	sync.Mutex
	decided bool
	folder  string
	instead string
}

func bookkeepingIn(root string) string {
	bookkeeping.Lock()
	defer bookkeeping.Unlock()

	if bookkeeping.decided {
		return bookkeeping.folder
	}

	bookkeeping.decided = true
	bookkeeping.folder = os.Getenv(pluginData)

	if bookkeeping.folder != "" && writableFolder(bookkeeping.folder) {
		return bookkeeping.folder
	}

	given := bookkeeping.folder
	space := workspace.At(workspace.ProjectRoot(root), "")
	bookkeeping.folder = space.SessionDir()
	if space.IsJournalDriven() {
		bookkeeping.folder = space.StateDir()
	}
	bookkeeping.instead = given

	return bookkeeping.folder
}

func BookkeepingSaid() string {
	bookkeeping.Lock()
	defer bookkeeping.Unlock()

	if !bookkeeping.decided || bookkeeping.instead == "" && bookkeeping.folder == os.Getenv(pluginData) {
		return ""
	}

	given := bookkeeping.instead
	if given == "" {
		given = "no folder of its own"
	}

	return "Code Commandments keeps its own bookkeeping in " + bookkeeping.folder +
		", because the journal gave it " + given + ", which it cannot write."
}

func writableFolder(folder string) bool {
	if err := os.MkdirAll(folder, 0o755); err != nil {
		return false
	}

	probe, err := os.CreateTemp(folder, ".writable-*")
	if err != nil {
		return false
	}

	probe.Close()
	os.Remove(probe.Name())

	return true
}

func bookkeepingFile(root, name string) string {
	return filepath.Join(bookkeepingIn(root), name)
}
