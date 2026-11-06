package scan

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestAMinifiedFileIsLeftUnreadWhateverItsSize(t *testing.T) {
	folder := t.TempDir()

	for name, contents := range map[string]string{
		"bundle.js":  strings.Repeat("a", smallestBundle) + ";var x=1," + strings.Repeat("b", longestWrittenLine),
		"written.js": strings.Repeat("const written = 1;\n", smallestBundle/8),
		"short.js":   "const x = 1;\n",
	} {
		if err := os.WriteFile(filepath.Join(folder, name), []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	for name, want := range map[string]bool{"bundle.js": true, "written.js": false, "short.js": false} {
		path := filepath.Join(folder, name)
		info, err := os.Stat(path)
		if err != nil {
			t.Fatal(err)
		}

		if got := unreadable(path, info.Size()); got != want {
			t.Errorf("%s is left unread: %v, want %v (%d bytes)", name, got, want, info.Size())
		}
	}
}
