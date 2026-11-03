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

// A worker judges its own task's changes, and a task that changed one Go file used to walk every Python file of
// the tree to conclude that a Python rule has nothing to judge: four seconds for an answer that needs the scan
// not to happen. The names of the changed files answer it.

// TestChangesOfAnotherLanguageAreAnsweredBeforeAnyScan holds the reading itself: a scoped run whose files are of
// no language the chosen rules read judges nothing, and one that holds a single file of such a language does not.
func TestChangesOfAnotherLanguageAreAnsweredBeforeAnyScan(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for _, name := range []string{"shop/till.go", "shop/till.py"} {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, nil, 0o644); err != nil {
			t.Fatal(err)
		}
	}

	goFile := filepath.Join(root, "shop/till.go")
	pythonFile := filepath.Join(root, "shop/till.py")

	for name, each := range map[string]struct {
		files     []string
		languages []source.Language
		answered  bool
	}{
		"a Go change and Python rules":        {[]string{goFile}, []source.Language{source.Python}, true},
		"a Go change and Go rules":            {[]string{goFile}, []source.Language{source.Go}, false},
		"a change in Python and Python rules": {[]string{pythonFile}, []source.Language{source.Python}, false},
		// A run that names no files judges the whole path, so it is never answered this way.
		"an unscoped run": {nil, []source.Language{source.Python}, false},
		// And a run whose rules read nothing is not answered here either: it has its own answer to give.
		"rules that read no language": {[]string{goFile}, nil, false},
	} {
		t.Run(name, func(t *testing.T) {
			targets, err := scope.FromArgs(nil, root, workspace.At(root, ""), nil)
			if err != nil {
				t.Fatal(err)
			}

			// A change scopes the run to its own files, which is what `--changes` hands the judge.
			for _, file := range each.files {
				targets = targets.NarrowedTo(file)
			}

			if answered := ofNoLanguage(targets, each.languages); answered != each.answered {
				t.Errorf("answered before scanning: %v, want %v", answered, each.answered)
			}
		})
	}
}
