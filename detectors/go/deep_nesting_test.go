package golang_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	godetectors "github.com/jessegall/code-commandments/detectors/go"
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/engine/golang"
)

// nested is a Go file holding the shape the rule is about and the shapes it must leave alone: a choice four deep
// is flagged, three deep is not, an else-if continues the `if` before it rather than nesting in it, and a
// function inside another starts the count again.
const nested = `package shop

// @sin DeepNesting
func settle(orders []Order) int {
	paid := 0
	for _, order := range orders {
		if order.Open {
			switch order.Kind {
			case "card":
				if order.Total > 0 {
					paid++
				}
			}
		}
	}

	return paid
}

func three(orders []Order) int {
	paid := 0
	for _, order := range orders {
		if order.Open {
			if order.Total > 0 {
				paid++
			}
		}
	}

	return paid
}

func ladder(kind string) int {
	if kind == "card" {
		return 1
	} else if kind == "cash" {
		return 2
	} else if kind == "voucher" {
		return 3
	} else if kind == "credit" {
		return 4
	}

	return 0
}

func inner(orders []Order) func() int {
	return func() int {
		paid := 0
		for _, order := range orders {
			if order.Open {
				if order.Total > 0 {
					paid++
				}
			}
		}

		return paid
	}
}

// Order is what the rule's shapes are written over.
type Order struct {
	Open  bool
	Kind  string
	Total int
}
`

// TestTheFirstGoRuleFiresThroughTheEngineItself holds the whole way from a Go file to a finding: the scan walks
// the tree, the funnel reaches the in-process bridge, the engine holds the codebase, and the detector reads it
// through the same selectors, Where and Reject every other engine's detector is read through.
func TestTheFirstGoRuleFiresThroughTheEngineItself(t *testing.T) {
	root := goFolder(t, map[string]string{"go.mod": "module example.com/shop\n", "settle.go": nested})

	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Only(source.Go).Load()
	if err != nil {
		t.Fatalf("the scan did not read the Go tree: %v", err)
	}

	found := godetectors.DeepNestingDetector{}.Find(codebase)

	if len(found) != 1 {
		t.Fatalf("the rule found %d choices four deep: %v", len(found), locations(found))
	}
	if held := found[0]; held.Kind() != "IfStmt" {
		t.Errorf("the finding is a %s at %s", held.Kind(), held.Location())
	}
	if scope := found[0].EnclosingFunction(); scope.Name() != "settle" {
		t.Errorf("the finding sits in %q, and only settle buries a choice four deep", scope.Name())
	}
}

// TestTheMarkerTheFixtureWritesIsReadAsAComment holds the fixture's own marker to the tree: a Go fixture marks a
// sin with `// @sin Name`, which the harness reads from the file's comments, so no second marker syntax is needed
// for Go.
func TestTheMarkerTheFixtureWritesIsReadAsAComment(t *testing.T) {
	root := goFolder(t, map[string]string{"go.mod": "module example.com/shop\n", "settle.go": nested})

	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Only(source.Go).Load()
	if err != nil {
		t.Fatal(err)
	}

	for _, file := range codebase.Files() {
		for _, comment := range file.File.Comments {
			if comment.Text == "// @sin DeepNesting" {
				if comment.Attached == nil {
					t.Error("the marker belongs to no node, so the harness could not tell what it marks")
				}

				return
			}
		}
	}
	t.Error("the file's comments hold no marker, though the fixture writes one")
}

// goFolder writes the files under a fresh folder and answers its resolved path.
func goFolder(t *testing.T, files map[string]string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range files {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

// locations are where each finding is, for a failure to read.
func locations(found []engine.Match) []string {
	var where []string
	for _, held := range found {
		where = append(where, held.Location())
	}

	return where
}
