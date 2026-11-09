package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
)

func TestAReadCountsEachFileItReturnedOnceAndAnEditCountsOnlyWhatItReads(t *testing.T) {
	root, edited, sibling := goProject(t)
	costs := ledger.Of(root)

	whole, err := loadCounted(root, scan.Walk([]string{root}, source.Excluded{}))
	if err != nil {
		t.Fatal(err)
	}

	if counted := costs.Count(ledger.FilesParsedByBridges); counted != len(whole.Files()) || counted < 2 {
		t.Errorf("a read of %d files counted %d, which must be each file once", len(whole.Files()), counted)
	}

	before := costs.Count(ledger.FilesParsedByBridges)

	reparsed, err := loadCounted(root, scan.OneFile(edited))
	if err != nil {
		t.Fatal(err)
	}

	if moved := costs.Count(ledger.FilesParsedByBridges) - before; moved != len(reparsed.Files()) {
		t.Errorf("an edit of %s read %d files and moved the count by %d (its sibling %s is read with it)",
			filepath.Base(edited), len(reparsed.Files()), moved, filepath.Base(sibling))
	}
}

func TestATreeBroughtBackFromDiskCountsNoParsedFile(t *testing.T) {
	root, edited, _ := goProject(t)
	roots := []string{root}
	named := branchRoot{project: root, branch: "main"}

	codebase, err := scan.Walk(roots, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}

	var files []string
	for _, file := range codebase.Files() {
		files = append(files, file.File.Path)
	}

	folder := filepath.Join(t.TempDir(), "tree")
	if err := warm.Keep().Hold(root, "main", roots, codebase, warm.Taken(files)).Save(folder, "binary", time.Now()); err != nil {
		t.Fatal(err)
	}

	saved, err := warm.LoadSaved(folder, "binary", roots, time.Now())
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { kept.Drop(root, "main") })

	if !broughtBackFromDisk(named, roots, files, saved, folder, time.Now()) {
		t.Fatal("an unchanged saved tree was not brought back")
	}

	if counted := ledger.Of(root).Count(ledger.FilesParsedByBridges); counted != 0 {
		t.Errorf("loading a saved tree counted %d files parsed; a load is not a parse", counted)
	}

	if err := os.WriteFile(edited, []byte("package shop\n\nfunc Post() int { return 0 }\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	kept.Drop(root, "main")
	if !broughtBackFromDisk(named, roots, files, saved, folder, time.Now()) {
		t.Fatal("a saved tree with one changed file was not brought back")
	}

	if counted := ledger.Of(root).Count(ledger.FilesParsedByBridges); counted == 0 {
		t.Error("the changed file was caught up and no parse was counted")
	}
}
