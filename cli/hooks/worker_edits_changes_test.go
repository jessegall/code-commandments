package hooks

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAPathEditedTwiceIsKeptOnce(t *testing.T) {
	data := t.TempDir()
	edits := WorkerEditsIn(data)

	edits.KeepIn(7, "/checkout", "src/twice.py")
	edits.KeepIn(7, "/checkout", "src/twice.py")

	raw, err := os.ReadFile(filepath.Join(data, workerEditsFolder, "7"))
	if err != nil || string(raw) != "src/twice.py\n" {
		t.Fatalf("the list on disk is %q: %v", raw, err)
	}
}
