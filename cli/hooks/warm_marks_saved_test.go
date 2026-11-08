package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/warm"
)

func TestASavedTreeCatchesUpOnWhatChangedAppearedOrWasDeleted(t *testing.T) {
	folder := t.TempDir()

	path := func(name string) string { return filepath.Join(folder, name) }
	write := func(name, body string) {
		if err := os.WriteFile(path(name), []byte(body), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	write("same.py", "a = 1\n")
	write("changed.py", "a = 1\n")
	write("deleted.py", "a = 1\n")

	marks := warm.Taken([]string{path("same.py"), path("changed.py"), path("deleted.py")})

	write("changed.py", "a = 22\n")
	write("new.py", "a = 1\n")

	if err := os.Remove(path("deleted.py")); err != nil {
		t.Fatal(err)
	}

	moved := movedSince(marks, []string{path("same.py"), path("changed.py"), path("new.py")})
	slices.Sort(moved)

	if want := []string{path("changed.py"), path("deleted.py"), path("new.py")}; !slices.Equal(moved, want) {
		t.Errorf("moved since the save: %v, want %v", moved, want)
	}
}
