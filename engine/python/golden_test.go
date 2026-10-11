package python_test

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python"
)

// fixture is tests/Fixtures/python as the Go engine reads it, loaded once for every golden test.
var fixture struct {
	once     sync.Once
	root     string
	codebase *python.Codebase
	err      error
}

// shop is the Python fixture's codebase, or the test skipped when no mypy is at hand.
func shopFixture(t *testing.T) *python.Codebase {
	t.Helper()
	command := bridgetest.Mypy(t)
	fixture.once.Do(func() {
		fixture.root, fixture.err = filepath.Abs("../../tests/Fixtures/python")
		stream, err := bridge.Once(command, fixture.root)
		if err != nil {
			fixture.err = err
			return
		}
		fixture.codebase = python.In(engine.Load(stream))
	})
	if fixture.err != nil {
		t.Fatal(fixture.err)
	}

	return fixture.codebase
}

// golden is what the PHP engine answered for the analysis, recorded before it was removed.
func golden(t *testing.T, analysis string, into any) {
	t.Helper()
	raw, err := os.ReadFile(filepath.Join("testdata", "golden", analysis+".json"))
	if err != nil {
		t.Fatal(err)
	}
	if err := json.Unmarshal(raw, into); err != nil {
		t.Fatal(err)
	}
}

// place is where a node is, as the golden files write it: its path under the fixture and its line.
func place(node python.Node) string {
	return strings.TrimPrefix(node.File(), fixture.root+"/") + ":" + itoa(node.Line())
}

func itoa(n int) string {
	raw, _ := json.Marshal(n)

	return string(raw)
}

// defs is every def of the fixture by its symbol id.
func defs(codebase *python.Codebase) map[string][]python.Node {
	found := map[string][]python.Node{}
	for _, match := range codebase.WhereFunction().Get() {
		found[match.Node().Symbol] = append(found[match.Node().Symbol], python.Node{Match: match})
	}

	return found
}

// unrecorded says whether the golden holds no answer for the key. The PHP engine was removed once these were
// recorded, so it never answered for anything the fixture gained afterwards; a key it never saw is skipped rather
// than read as an empty answer, which would hold every file the fixture gains to a reach, a hash and an import
// list of nothing. Everything it did record still binds, so a change to code it saw still fails here.
func unrecorded[T any](want map[string]T, key string) bool {
	_, held := want[key]

	return !held
}
