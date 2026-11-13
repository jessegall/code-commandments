package walk

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
)

// Every file under a folder of the tool's own is listed, hidden ones and those in a dependency folder too, and a linked
// folder is listed as itself, never gone into.
func TestAllFilesListsEveryFileOfAFolderAndNeverGoesThroughALink(t *testing.T) {
	dir, elsewhere := t.TempDir(), t.TempDir()
	for _, name := range []string{"lines/a.json", ".mark", "vendor/b.json"} {
		path := filepath.Join(dir, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, nil, 0o644); err != nil {
			t.Fatal(err)
		}
	}
	if err := os.WriteFile(filepath.Join(elsewhere, "c.json"), nil, 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink(elsewhere, filepath.Join(dir, "linked")); err != nil {
		t.Fatal(err)
	}

	want := []string{dir + "/.mark", dir + "/lines/a.json", dir + "/linked", dir + "/vendor/b.json"}
	if got := AllFiles(dir); !slices.Equal(got, want) {
		t.Errorf("AllFiles listed %q, not %q", got, want)
	}
}
