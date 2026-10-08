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

// TestARunOverOneFileJudgesOnlyThatFile holds `judge <file>`: the run reads the folder the file sits in, so its
// neighbours still inform the rules that read across files, but the file alone is judged and reported.
func TestARunOverOneFileJudgesOnlyThatFile(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for _, name := range []string{"orders/Order.php", "orders/Line.php"} {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte("<?php\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	folder, file := filepath.Join(root, "orders"), filepath.Join(root, "orders", "Order.php")
	targets, err := scope.FromArgs(nil, folder, workspace.At(root, ""), nil)
	if err != nil {
		t.Fatal(err)
	}
	targets = targets.NarrowedTo(file)

	if judged := judgedPaths(targets, scan.Walk([]string{folder}, source.Excluded{})); !slices.Equal(slices.Sorted(maps.Keys(judged)), []string{file}) {
		t.Errorf("a run over Order.php judged %v", slices.Sorted(maps.Keys(judged)))
	}
	if !targets.Includes(file) || targets.Includes(filepath.Join(folder, "Line.php")) {
		t.Errorf("Order.php in: %v, Line.php in: %v", targets.Includes(file), targets.Includes(filepath.Join(folder, "Line.php")))
	}
}
