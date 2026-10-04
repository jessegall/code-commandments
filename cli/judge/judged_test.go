package judge

import (
	"maps"
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// TestARunOverOneFolderJudgesOnlyItsFiles holds what a run tells the Sins dashboard it judged: a run over one
// folder names that folder's files, so the findings it did not judge stay on the dashboard.
func TestARunOverOneFolderJudgesOnlyItsFiles(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for _, name := range []string{"orders/Order.php", "carts/Cart.php"} {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte("<?php\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	folder := filepath.Join(root, "orders")
	targets, err := scope.FromArgs(nil, folder, workspace.At(root, ""), nil)
	if err != nil {
		t.Fatal(err)
	}

	judged := judgedPaths(targets, scan.Walk([]string{folder}, source.Excluded{}))

	if got := slices.Sorted(maps.Keys(judged)); !slices.Equal(got, []string{filepath.Join(folder, "Order.php")}) {
		t.Errorf("a run over orders/ judged %v", got)
	}
}
