package scan_test

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/rules"
)

func TestASolutionIsCutIntoOneUnitPerProject(t *testing.T) {
	root, err := filepath.Abs("testdata/solution")
	if err != nil {
		t.Fatal(err)
	}
	root, _ = filepath.EvalSymlinks(root)
	bridgetest.Roslyn(t, root)
	sources := scan.Walk([]string{root}, source.Excluded{}).Only(source.CSharp)

	var completed [][]string
	var judged []string
	units, err := sources.Units(func(codebase *engine.Codebase) error {
		completed = append(completed, pathsOf(codebase))
		judged = append(judged, judgedOf(codebase)...)

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
		judged = append(judged, judgedOf(codebase)...)
	}

	whole, err := sources.Load()
	if err != nil {
		t.Fatal(err)
	}
	slices.Sort(every)
	if want := pathsOf(whole); !slices.Equal(every, want) {
		t.Errorf("the units hold %v, the whole solution %v", every, want)
	}
	for _, path := range judged {
		if !slices.Contains(judgedOf(whole), path) {
			t.Errorf("a unit reads a file as %s, which the whole solution does not: %v", path, judgedOf(whole))
		}
	}
}

func judgedOf(codebase *engine.Codebase) []string {
	var paths []string
	for _, file := range codebase.Files() {
		paths = append(paths, file.FromProject())
	}

	return paths
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

// A solution under the budget is judged as one codebase, so a rule reading across its projects (a cycle between
// namespaces, a function or a parameter group repeated in two projects) finds in its units what it finds whole.
func TestASolutionThatFitsIsJudgedThroughItsUnitsAsOneCodebase(t *testing.T) {
	root, err := filepath.Abs("testdata/solution")
	if err != nil {
		t.Fatal(err)
	}
	root, _ = filepath.EvalSymlinks(root)
	bridgetest.Roslyn(t, root)
	sources := scan.Walk([]string{root}, source.Excluded{}).Only(source.CSharp)
	units, err := sources.Units(func(*engine.Codebase) error { return nil })
	if err != nil {
		t.Fatal(err)
	}
	defer units.Close()
	if !units.FitWhole() {
		t.Fatal("a two-project solution was read as too large to judge whole")
	}
	together, err := units.LoadWhole()
	if err != nil {
		t.Fatal(err)
	}
	whole, err := sources.Load()
	if err != nil {
		t.Fatal(err)
	}
	if got, want := pathsOf(together), pathsOf(whole); !slices.Equal(got, want) {
		t.Fatalf("the units together hold %v, the whole solution %v", got, want)
	}
	for _, rule := range rules.Of(catalog.CSharp) {
		if got, want := locationsOf(rule.Find(together)), locationsOf(rule.Find(whole)); !slices.Equal(got, want) {
			t.Errorf("%s finds %v through the units, %v whole", catalog.Name(rule), got, want)
		}
	}
}

func locationsOf(matches []engine.Match) []string {
	var at []string
	for _, match := range matches {
		at = append(at, match.Location())
	}
	slices.Sort(at)

	return at
}
