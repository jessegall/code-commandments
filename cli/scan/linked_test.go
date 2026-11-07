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

func TestAFolderLinkedBackIntoItsOwnParentIsNeverWalked(t *testing.T) {
	root, _ := filepath.EvalSymlinks(t.TempDir())
	if err := os.MkdirAll(filepath.Join(root, "src", "orders"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "src", "orders", "refund.py"), []byte("def refund(order) -> int:\n    return order.total\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink("..", filepath.Join(root, "src", "orders", "back")); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, ".cache"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, ".cache", "copy.py"), []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	files := scan.Walk([]string{root}, source.Excluded{}).Files(source.Languages...)

	if !slices.Equal(files, []string{filepath.Join(root, "src", "orders", "refund.py")}) {
		t.Errorf("the walk found %v, where a folder linked back into its own parent must not be walked, or the walk "+
			"goes round for ever, and a hidden folder is never judged", files)
	}
}
