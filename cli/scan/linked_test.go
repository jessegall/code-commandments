package scan_test

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

// TestAFileLinkedIntoTheTreeIsReadOnce holds the scan to reading a file once when the tree links it in a second
// time: agent-journal's install.py is a link to src/install.py, and the PHP tool judged both, calling each of its
// functions a copy of itself and leaving a call to one of them resolved to neither.
func TestAFileLinkedIntoTheTreeIsReadOnce(t *testing.T) {
	root, _ := filepath.EvalSymlinks(t.TempDir())
	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "src/install.py"), []byte("def install(path: str) -> str:\n    return path\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink("src/install.py", filepath.Join(root, "install.py")); err != nil {
		t.Fatal(err)
	}

	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}

	if paths := pathsOf(codebase); !slices.Equal(paths, []string{filepath.Join(root, "src/install.py")}) {
		t.Errorf("the linked file is read as %v", paths)
	}
}
