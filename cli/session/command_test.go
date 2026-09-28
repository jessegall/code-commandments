package session

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAFolderIsSizedAsAPFSReportsItOnEveryFilesystem(t *testing.T) {
	dir := t.TempDir()

	for entries, want := range []string{"64B", "96B", "128B"} {
		folder := filepath.Join(dir, "folder")

		if entries > 0 {
			if err := os.WriteFile(filepath.Join(folder, string(rune('a'+entries))), []byte("# sins\n"), 0o644); err != nil {
				t.Fatal(err)
			}
		} else if err := os.Mkdir(folder, 0o755); err != nil {
			t.Fatal(err)
		}

		if got := size(folder); got != want {
			t.Errorf("a folder of %d entries is %s, want %s", entries, got, want)
		}
	}
}

func TestAFileIsSizedByItsLength(t *testing.T) {
	file := filepath.Join(t.TempDir(), ".judge-counter")

	if err := os.WriteFile(file, make([]byte, 2500), 0o644); err != nil {
		t.Fatal(err)
	}

	if got := size(file); got != "2.4K" {
		t.Errorf("2500 bytes is %s, want 2.4K", got)
	}
}
