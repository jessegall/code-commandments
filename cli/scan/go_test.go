package scan_test

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
)

// TestGoReachesTheEngineThroughTheOneFunnel holds the Go bridge to the same door every other language's bridge
// comes through: the scan walks the tree, the funnel reaches the bridge, and the engine holds the codebase. What
// differs is only how the bridge is reached, which is the one thing a bridge declares for itself.
func TestGoReachesTheEngineThroughTheOneFunnel(t *testing.T) {
	root := goTree(t, map[string]string{
		"go.mod":  "module example.com/shop\n",
		"cart.go": "package shop\n\ntype Cart struct{ lines []int }\n\nfunc (c Cart) Total() int {\n\ttotal := 0\n\tfor _, line := range c.lines {\n\t\ttotal += line\n\t}\n\n\treturn total\n}\n",
	})

	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Only(source.Go).Load()
	if err != nil {
		t.Fatalf("the scan did not read the Go tree: %v", err)
	}

	files := codebase.Files()
	if len(files) != 1 {
		t.Fatalf("the codebase holds %d files", len(files))
	}
	if files[0].Language() != contract.Go {
		t.Errorf("the file is read as %s", files[0].Language())
	}

	var methods, loops int
	for _, node := range files[0].File.Nodes() {
		if node.Answers("function") {
			methods++
		}
		if node.Answers("loop") {
			loops++
		}
	}
	if methods != 1 || loops != 1 {
		t.Errorf("the tree holds %d functions and %d loops", methods, loops)
	}
}

// TestJudgingGoStartsNothingAndKeepsNothingWarm holds the one thing that makes this bridge different: it runs in
// this process. No command is started for it, no socket is dialled for it, and no service is kept up for it, so a
// run of the tool over a Go tree leaves nothing behind to orphan.
func TestJudgingGoStartsNothingAndKeepsNothingWarm(t *testing.T) {
	held, known := scan.Named("go")
	if !known {
		t.Fatal("the scan knows no bridge named go")
	}

	if _, kept, err := held.Reach.Own(held, []string{t.TempDir()}); kept || err != nil {
		t.Errorf("a service would be kept up for Go: kept %v, %v", kept, err)
	}

	asker, err := held.Reach.Open(held, []string{t.TempDir()})
	if err != nil {
		t.Fatalf("the bridge could not be opened: %v", err)
	}
	if asker == nil {
		t.Fatal("the bridge answered no asker")
	}
	if err := asker.Close(); err != nil {
		t.Errorf("closing a bridge that holds nothing answered %v", err)
	}

	socket := bridge.ServiceSocket("go", t.TempDir())
	if _, err := os.Stat(socket); err == nil {
		t.Errorf("a socket stands at %s for a bridge that has no process", socket)
	}
}

// TestAGoFileIsNotReadAsPHP holds the language table to a Go file: it defaulted to PHP for every extension it did
// not know, so a .go file was judged by the PHP rules, which is a wrong answer nothing reports.
func TestAGoFileIsNotReadAsPHP(t *testing.T) {
	for path, want := range map[string]source.Language{
		"cli/scan/bridges.go": source.Go,
		"src/Cart.php":        source.PHP,
		"src/cart.py":         source.Python,
		"src/Cart.cs":         source.CSharp,
	} {
		if held := source.OfFile(path); held != want {
			t.Errorf("%s is read as %s, not %s", path, held, want)
		}
	}
	if !source.Judges("cli/scan/bridges.go") {
		t.Error("a Go file is not one the tool judges")
	}
}

// goTree writes the files under a fresh folder and answers its resolved path.
func goTree(t *testing.T, files map[string]string) string {
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
	if !strings.HasSuffix(root, string(filepath.Separator)) {
		return root
	}

	return strings.TrimRight(root, string(filepath.Separator))
}
