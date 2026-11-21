package csharp_test

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp"
)

var fixture struct {
	once     sync.Once
	root     string
	codebase *engine.Codebase
	err      error
}

func shop(t *testing.T) *engine.Codebase {
	t.Helper()
	root, err := filepath.Abs("../../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	command := bridgetest.Roslyn(t, root)
	fixture.once.Do(func() {
		fixture.root, fixture.err = filepath.EvalSymlinks(root)
		if fixture.err != nil {
			return
		}
		stream, err := bridge.Once(command, bridge.CSharpProjects.Under(fixture.root).Arguments()...)
		if err != nil {
			fixture.err = err
			return
		}
		fixture.codebase = engine.Load(stream)
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

func at(node csharp.Node) string {
	return fmt.Sprintf("%s@%d:%s", strings.TrimPrefix(node.File(), fixture.root+"/"), node.Node().Span.Start, node.Kind())
}

func nodes(codebase *engine.Codebase) []csharp.Node {
	var all []csharp.Node
	for _, file := range csharp.In(codebase).Files() {
		for _, match := range file.Match(0).Descendants() {
			all = append(all, csharp.Node{Match: match})
		}
	}

	return all
}

func compare[T any](t *testing.T, what string, want, got map[string]T) {
	t.Helper()
	var keys []string
	for key := range want {
		keys = append(keys, key)
	}
	sort.Strings(keys)
	for _, key := range keys {
		wanted, wantedOk := want[key]
		gotten, gottenOk := got[key]
		if !wantedOk || !gottenOk || fmt.Sprint(wanted) != fmt.Sprint(gotten) {
			t.Errorf("%s %s: PHP %v (%v), Go %v (%v)", what, key, wanted, wantedOk, gotten, gottenOk)
		}
	}
}

type goldenType struct {
	Name     string   `json:"name"`
	Nullable bool     `json:"nullable"`
	Value    bool     `json:"value"`
	Inner    []string `json:"inner"`
}

func admitsAbsence(typed goldenType) goldenType {
	if typed.Value && strings.HasSuffix(typed.Name, "?") {
		typed.Nullable = true
	}

	return typed
}

type goldenTarget struct {
	Type       string   `json:"type"`
	Name       string   `json:"name"`
	Parameters []string `json:"parameters"`
}

func withoutArguments(name string) string {
	if open := strings.Index(name, "<"); open >= 0 {
		return name[:open]
	}

	return name
}

type goldenFlow struct {
	State         map[string]string   `json:"state"`
	ReadsByMember map[string][]string `json:"readsByMember"`
	Nullable      []string            `json:"nullable"`
	Assigned      map[string][]string `json:"assigned"`
	Reads         map[string]int      `json:"reads"`
	Coupled       bool                `json:"coupled"`
}

type goldenMethod struct {
	Callers   []string `json:"callers"`
	HandedOut bool     `json:"handedOut"`
	Envied    string   `json:"envied"`
	Unpacks   bool     `json:"unpacks"`
}

type goldenDeclaration struct {
	Declared bool     `json:"declared"`
	Record   bool     `json:"record"`
	Enum     []string `json:"enum"`
	Cased    bool     `json:"cased"`
}

