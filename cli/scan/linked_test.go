package scan_test

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

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
