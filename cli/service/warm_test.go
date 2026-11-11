package service

import (
	"bytes"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/contract"
)

func TestWarmingReadsWhatAJudgeWouldAsk(t *testing.T) {
	root := project(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"], "exclude": ["src/generated"]}`,
		"src/cart.py":               "def total(prices: list[int]) -> int:\n    return sum(prices)\n",
		"src/Cart.cs":               "namespace Shop;\n\npublic sealed class Cart { }\n",
		"src/generated/schema.py":   "schema = {}\n",
		"tests/test_cart.py":        "def test_total() -> None:\n    pass\n",
	})

	warmed := filesOf(bridgeNamed(t, "mypy"), root)

	if want := []string{filepath.Join(root, "src", "cart.py")}; !slices.Equal(warmed, want) {
		t.Errorf("the warm-up reads %v, not the project's own Python", warmed)
	}
}

func TestAWarmedBridgeHoldsTheProject(t *testing.T) {
	root := project(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/cart.py":               "def total(prices: list[int]) -> int:\n    return sum(prices)\n",
	})

	server, err := bridge.Serve(bridgetest.Mypy(t))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()

	var out, errs bytes.Buffer
	warm(bridgeNamed(t, "mypy"), root, server, cli.Console{Out: &out, Err: &errs})

	if errs.Len() > 0 {
		t.Fatalf("the warm-up warned: %s", errs.String())
	}
	if said := out.String(); !bytes.Contains([]byte(said), []byte("1 file")) {
		t.Errorf("the warm-up says %q, not what it read", said)
	}

	stream, err := server.Ask(bridge.Request{Paths: []string{filepath.Join(root, "src", "cart.py")}})
	if err != nil {
		t.Fatal(err)
	}
	if len(stream.Files) != 1 {
		t.Errorf("the warmed bridge answered %d files", len(stream.Files))
	}
}

func TestAWarmUpThatCannotRunLeavesTheServiceServing(t *testing.T) {
	root := project(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/cart.py":               "def total(prices: list[int]) -> int:\n    return sum(prices)\n",
	})

	server, err := bridge.Serve(bridgetest.Mypy(t))
	if err != nil {
		t.Fatal(err)
	}
	server.Close()

	var out, errs bytes.Buffer
	warm(bridgeNamed(t, "mypy"), root, server, cli.Console{Out: &out, Err: &errs})

	if errs.Len() == 0 {
		t.Error("a warm-up that could not read the project said nothing")
	}
}

// counting is a bridge that answers nothing and keeps how many files each request named.
type counting struct {
	asked []int
}

func (c *counting) AskTallied(request bridge.Request, tally func()) (*contract.Stream, error) {
	c.asked = append(c.asked, len(request.Write))

	return &contract.Stream{}, nil
}

func (c *counting) AskEach(bridge.Request, func(contract.Line, []byte) error) error {
	return nil
}

func (c *counting) Close() error {
	return nil
}

func manyPythonFiles(count int) map[string]string {
	files := map[string]string{".commandments/config.json": `{"paths": ["src"]}`}
	for at := range count {
		files[fmt.Sprintf("src/module_%03d.py", at)] = "held = 1\n"
	}

	return files
}

func TestAWarmUpAsksABridgeForItsFilesInGroups(t *testing.T) {
	root := project(t, manyPythonFiles(warmedAtOnce*2+1))
	asked := &counting{}

	var out, errs bytes.Buffer
	warm(bridgeNamed(t, "mypy"), root, asked, cli.Console{Out: &out, Err: &errs})

	if want := []int{warmedAtOnce, warmedAtOnce, 1}; !slices.Equal(asked.asked, want) {
		t.Errorf("the warm-up asked for %v files a request, want %v", asked.asked, want)
	}
}

func TestAWarmUpAsksABridgeThatReadsWholeRootsOnce(t *testing.T) {
	if got := warmedTogether(bridgeNamed(t, "roslyn"), 5000); got != 5000 {
		t.Errorf("a bridge that reads whole roots is warmed %d files at a time, not all 5000 at once", got)
	}
}

func project(t *testing.T, files map[string]string) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	for path, body := range files {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte(body), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func bridgeNamed(t *testing.T, kind string) Bridge {
	t.Helper()

	held, known := scan.Named(kind)
	if !known {
		t.Fatalf("no bridge is named %q", kind)
	}

	return held
}
