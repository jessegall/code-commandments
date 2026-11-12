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

var fixture struct {
	once     sync.Once
	root     string
	codebase *python.Codebase
	err      error
}

func shopFixture(t *testing.T) *python.Codebase {
	t.Helper()
	command := bridgetest.Mypy(t)
	fixture.once.Do(func() {
		fixture.root, fixture.err = filepath.Abs("../../tests/Fixtures/python")
		stream, err := bridge.Once(command, bridge.Listed(fixture.root)...)
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

func place(node python.Node) string {
	return strings.TrimPrefix(node.File(), fixture.root+"/") + ":" + itoa(node.Line())
}

func itoa(n int) string {
	raw, _ := json.Marshal(n)

	return string(raw)
}

func defs(codebase *python.Codebase) map[string][]python.Node {
	found := map[string][]python.Node{}
	for _, match := range codebase.WhereFunction().Get() {
		found[match.Node().Symbol] = append(found[match.Node().Symbol], python.Node{Match: match})
	}

	return found
}

func unrecorded[T any](want map[string]T, key string) bool {
	_, held := want[key]

	return !held
}

func recordedFiles[T any](want map[string]T) map[string]bool {
	files := map[string]bool{}
	for key := range want {
		_, place, found := strings.Cut(key, "@")
		if !found {
			continue
		}
		if path := place[:strings.LastIndex(place, ":")]; path != "" {
			files[path] = true
		}
	}

	return files
}
