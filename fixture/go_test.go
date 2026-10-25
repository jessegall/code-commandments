package fixture_test

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge/gotree"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/engine/golang"
	"github.com/jessegall/code-commandments/fixture"
)

// goFixture is where the Go fixture lives, from this package.
const goFixture = "../tests/Fixtures/go"

// TestTheGoFixtureIsAModuleOfItsOwn holds the fixture out of the tool's own build. A fixture holds code written
// to be judged, not code written to compile cleanly: an unused import, an unused variable or a missing return is
// a scenario here and a compile error to Go, and one of them in a package of this module would break the suite,
// the pre-commit generators and every release until it was taken out again.
func TestTheGoFixtureIsAModuleOfItsOwn(t *testing.T) {
	if _, err := os.Stat(filepath.Join(goFixture, "go.mod")); err != nil {
		t.Fatalf("the fixture declares no module of its own: %v", err)
	}

	listed, err := exec.Command("go", "list", "./...").Output()
	if err != nil {
		t.Skipf("no go list here: %v", err)
	}
	for _, line := range strings.Split(string(listed), "\n") {
		if strings.Contains(line, "tests/Fixtures/go") {
			t.Errorf("the tool's own packages include %s, so a fixture that does not compile would break the build", line)
		}
	}
}

// TestTheGoFixtureCompilesOnItsOwnTerms holds the fixture to its own module's vet: every scenario in it is code
// Go accepts, so a reader can tell a deliberate sin from a mistake. A scenario that needs code Go refuses is
// named in the fixture's README, and there are none today.
func TestTheGoFixtureCompilesOnItsOwnTerms(t *testing.T) {
	if _, err := exec.LookPath("go"); err != nil {
		t.Skipf("no go here: %v", err)
	}

	vet := exec.Command("go", "vet", "./...")
	vet.Dir = goFixture
	if out, err := vet.CombinedOutput(); err != nil {
		t.Errorf("the fixture does not pass its own vet: %v\n%s", err, out)
	}
}

// TestTheGoFixtureIsFormattedAsGoWritesIt holds the markers to gofmt. gofmt reflows a doc comment and moves a
// comment that sits where Go would not leave one, so a fixture it would rewrite is a fixture whose markers move
// under anyone who runs it.
func TestTheGoFixtureIsFormattedAsGoWritesIt(t *testing.T) {
	if _, err := exec.LookPath("gofmt"); err != nil {
		t.Skipf("no gofmt here: %v", err)
	}

	out, err := exec.Command("gofmt", "-l", goFixture).Output()
	if err != nil {
		t.Fatalf("gofmt: %v", err)
	}
	if named := strings.TrimSpace(string(out)); named != "" {
		t.Errorf("gofmt would rewrite %s, which moves the markers in it", named)
	}
}

// TestAMarkerIsReadAndDocumentsNothing holds a Go marker to both halves of what it must be: the harness reads it,
// and the declaration under it is not left looking documented.
//
// Go's own parser gives a `//` group directly above a declaration to that declaration as its documentation, and
// the tree says so, so a marker written there would make every marked declaration read as documented. A blank
// line between the two leaves the marker a comment of its own. Inside a body there is no documentation to be.
func TestAMarkerIsReadAndDocumentsNothing(t *testing.T) {
	codebase := goCodebase(t)

	markers := fixture.Markers(codebase)
	if len(markers) == 0 {
		t.Fatal("the harness read no marker in the Go fixture")
	}

	var marked int
	for _, marker := range markers {
		if marker.Name == "DeepNesting" {
			marked++
		}
	}
	if marked < 3 {
		t.Errorf("the harness read %d DeepNesting markers, where the fixture writes a sin, a fix and a righteous twin", marked)
	}

	for _, declaration := range codebase.WhereFunction().Get() {
		if declaration.IsDocumented() && documentedByAMarker(declaration) {
			t.Errorf("%s reads as documented by a marker", declaration.Name())
		}
	}
}

// TestAScenarioIsTheFunctionTheFindingSitsIn holds the harness's own scenario reading for Go. Go has no class
// body, so a finding's scope is the function it sits in, which is the unit a reader of Go reads: two findings in
// two functions are two scenarios, where a language with a class shell would have counted the shell as shared.
func TestAScenarioIsTheFunctionTheFindingSitsIn(t *testing.T) {
	codebase := goCodebase(t)

	dispatch := functionNamed(t, codebase, "Dispatch")
	weigh := functionNamed(t, codebase, "Weigh")

	if dispatch.EnclosingType().Exists() {
		t.Error("a Go method sits in a type declaration, which Go does not write")
	}
	if dispatch.Location() == weigh.Location() {
		t.Error("two methods of one type share a scope, so every scenario in a file would read as one")
	}
}

// documentedByAMarker says whether every doc comment the node carries is a marker.
func documentedByAMarker(node engine.Match) bool {
	for _, comment := range node.Comments() {
		if comment.Kind != "doc" {
			continue
		}
		if !strings.Contains(comment.Text, "@sin") && !strings.Contains(comment.Text, "@fixed") && !strings.Contains(comment.Text, "@righteous") {
			return false
		}
	}

	return true
}

// goCodebase is the Go fixture as the engine holds it, read through the real bridge.
func goCodebase(t *testing.T) *engine.Codebase {
	t.Helper()
	root, err := filepath.Abs(goFixture)
	if err != nil {
		t.Fatal(err)
	}

	var written bytes.Buffer
	if err := gotree.Write(&written, []string{root}, nil); err != nil {
		t.Fatalf("the bridge did not read the fixture: %v", err)
	}
	stream, err := contract.ReadAll(bytes.NewReader(written.Bytes()))
	if err != nil {
		t.Fatalf("the fixture's stream breaks the contract: %v", err)
	}

	return engine.Load(stream)
}

// functionNamed is the fixture's function declaration of that name.
func functionNamed(t *testing.T, codebase *engine.Codebase, name string) engine.Match {
	t.Helper()
	for _, held := range codebase.WhereFunction().Get() {
		if held.Name() == name {
			return held
		}
	}
	t.Fatalf("the fixture holds no function %s", name)

	return engine.Match{}
}
