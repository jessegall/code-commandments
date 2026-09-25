package scan_test

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
)

func TestASolutionIsCutIntoOneUnitPerProject(t *testing.T) {
	root, err := filepath.Abs("testdata/solution")
	if err != nil {
		t.Fatal(err)
	}
	root, _ = filepath.EvalSymlinks(root)
	bridge.TestRoslyn(t, root)
	sources := scan.Walk([]string{root}, source.Excluded{}).Only(source.CSharp)

	var completed [][]string
	units, err := sources.CSharpUnits(func(codebase *engine.Codebase) error {
		completed = append(completed, pathsOf(codebase))

		return nil
	})
	if err != nil {
		t.Fatal(err)
	}
	defer units.Close()

	if units.Count() != 2 || len(completed) != 2 {
		t.Fatalf("want a unit for Core and one for App, got %d units and %d completed: %v", units.Count(), len(completed), completed)
	}
	var every []string
	for at := range units.Count() {
		codebase, err := units.Load(at)
		if err != nil {
			t.Fatal(err)
		}
		loaded := pathsOf(codebase)
		if !slices.Equal(loaded, completed[at]) {
			t.Errorf("unit %d reads back %v, was completed as %v", at, loaded, completed[at])
		}
		if owners := ownersOf(loaded); len(owners) != 1 {
			t.Errorf("unit %d spans the projects %v", at, owners)
		}
		every = append(every, loaded...)
	}

	whole, err := sources.Load()
	if err != nil {
		t.Fatal(err)
	}
	slices.Sort(every)
	if want := pathsOf(whole); !slices.Equal(every, want) {
		t.Errorf("the units hold %v, the whole solution %v", every, want)
	}
}

func pathsOf(codebase *engine.Codebase) []string {
	var paths []string
	for _, file := range codebase.Files() {
		paths = append(paths, file.Path)
	}
	slices.Sort(paths)

	return paths
}

func ownersOf(paths []string) []string {
	var owners []string
	for _, path := range paths {
		if owner := filepath.Base(filepath.Dir(path)); !slices.Contains(owners, owner) {
			owners = append(owners, owner)
		}
	}

	return owners
}
