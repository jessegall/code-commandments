package csharp_test

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp"
)

// fixture is tests/Fixtures/csharp as the Go engine reads it, loaded once for every golden test.
var fixture struct {
	once     sync.Once
	root     string
	codebase *engine.Codebase
	err      error
}

// shop is the C# fixture's codebase, or the test skipped when there is no Docker to run the bridge in.
func shop(t *testing.T) *engine.Codebase {
	t.Helper()
	root, err := filepath.Abs("../../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	command := bridge.TestRoslyn(t, root)
	fixture.once.Do(func() {
		fixture.root, fixture.err = filepath.EvalSymlinks(root)
		if fixture.err != nil {
			return
		}
		stream, err := bridge.Once(command, fixture.root)
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

// golden is what the PHP engine answered for the analysis, written by testdata/golden.php.
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

// at is where a node is, as the golden files write it: its path under the fixture, its byte offset and its kind.
func at(node csharp.Node) string {
	return fmt.Sprintf("%s@%d:%s", strings.TrimPrefix(node.File(), fixture.root+"/"), node.Node().Span.Start, node.Kind())
}

// nodes is every C# node of the codebase but the files' roots.
func nodes(codebase *engine.Codebase) []csharp.Node {
	var all []csharp.Node
	for _, file := range csharp.In(codebase).Files() {
		for _, match := range file.Match(0).Descendants() {
			all = append(all, csharp.Node{Match: match})
		}
	}

	return all
}

// compare fails once per key whose answers differ, and for every key only one side has.
func compare[T any](t *testing.T, what string, want, got map[string]T) {
	t.Helper()
	var keys []string
	for key := range want {
		keys = append(keys, key)
	}
	for key := range got {
		if _, held := want[key]; !held {
			keys = append(keys, key)
		}
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

// admitsAbsence is the type as the tree's `nullable` reads it: a `T?` value admits absence, where the PHP engine
// reported the compiler's annotation, which flow analysis can clear.
func admitsAbsence(typed goldenType) goldenType {
	if typed.Value && strings.HasSuffix(typed.Name, "?") {
		typed.Nullable = true
	}

	return typed
}

func TestEveryNodeStandsForTheTypeThePhpEngineGivesIt(t *testing.T) {
	codebase := shop(t)
	var want map[string]goldenType
	golden(t, "types", &want)
	got := map[string]goldenType{}
	for _, node := range nodes(codebase) {
		if typed := node.Type(); typed.Exists() {
			got[at(node)] = admitsAbsence(goldenType{Name: typed.Name(), Nullable: typed.IsNullable(), Value: typed.IsValueType(), Inner: typed.Inner()})
		}
	}
	for key, typed := range want {
		want[key] = admitsAbsence(typed)
	}
	compare(t, "type", want, got)
}

type goldenTarget struct {
	Type       string   `json:"type"`
	Name       string   `json:"name"`
	Parameters []string `json:"parameters"`
}

// withoutArguments is a type named without its generic arguments: `List<T>` and `List<String>` are one type.
func withoutArguments(name string) string {
	if open := strings.Index(name, "<"); open >= 0 {
		return name[:open]
	}

	return name
}

// The PHP engine bound a call to the method as constructed (`List<String>.Add(String)`, an extension method without
// its `this`); the tree names the original definition, so a call joins its declaration, and carries the parameter
// types as the call binds them. The type is compared without its arguments; the parameters as they are.
func TestEveryCallReachesTheMethodThePhpEngineBindsItTo(t *testing.T) {
	codebase := shop(t)
	var want map[string]goldenTarget
	golden(t, "targets", &want)
	got := map[string]goldenTarget{}
	for _, node := range nodes(codebase) {
		target := node.Target()
		if !target.Exists() {
			continue
		}
		wanted := want[at(node)]
		got[at(node)] = goldenTarget{Type: withoutArguments(target.Type()), Name: target.Name(), Parameters: target.Parameters()}
		want[at(node)] = goldenTarget{Type: withoutArguments(wanted.Type), Name: wanted.Name, Parameters: wanted.Parameters}
	}
	compare(t, "target", want, got)
}

type goldenFlow struct {
	State         map[string]string   `json:"state"`
	ReadsByMember map[string][]string `json:"readsByMember"`
	Nullable      []string            `json:"nullable"`
	Assigned      map[string][]string `json:"assigned"`
	Reads         map[string]int      `json:"reads"`
	Coupled       bool                `json:"coupled"`
}

func TestEveryTypesStateFlowsAsThePhpEngineReadsIt(t *testing.T) {
	codebase := shop(t)
	program := csharp.Of(codebase)
	var want map[string]goldenFlow
	golden(t, "flows", &want)
	got := map[string]goldenFlow{}
	for _, match := range csharp.In(codebase).WhereType().Get() {
		owner := csharp.Node{Match: match}
		flow := csharp.FlowOf(owner)
		answer := goldenFlow{State: map[string]string{}, ReadsByMember: map[string][]string{}, Assigned: map[string][]string{}, Reads: map[string]int{}, Coupled: owner.HoldsCoupledFields(program)}
		for _, state := range owner.StateTypes() {
			answer.State[state.Name] = state.Type.Name()
			var assigned []string
			for _, value := range flow.AssignedValues(state.Name) {
				assigned = append(assigned, at(value))
			}
			answer.Assigned[state.Name] = assigned
			answer.Reads[state.Name] = len(flow.Reads(state.Name))
		}
		for _, member := range flow.ReadsByMember() {
			answer.ReadsByMember[member.Member] = member.Reads
		}
		answer.Nullable = flow.NullableFields()
		got[at(owner)] = answer
	}
	compare(t, "flow", want, got)
}

func TestTheNamespaceGraphHoldsThePhpEnginesArrows(t *testing.T) {
	codebase := shop(t)
	var want struct {
		Arrows []string `json:"arrows"`
	}
	golden(t, "namespaces", &want)
	var got []string
	for _, arrow := range csharp.Namespaces(codebase).Arrows() {
		got = append(got, fmt.Sprintf("%s %s -> %s", at(csharp.Node{Match: arrow.At}), arrow.From, arrow.To))
	}
	sort.Strings(got)
	for _, arrow := range want.Arrows {
		if !slices.Contains(got, arrow) {
			t.Errorf("Go has no arrow %s", arrow)
		}
	}
	for _, arrow := range got {
		if !slices.Contains(want.Arrows, arrow) {
			t.Errorf("PHP has no arrow %s", arrow)
		}
	}
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

// An extension method's callers are the program's own: the PHP engine's reduced targets never joined them.
func TestTheProgramReadsWholeAsThePhpEngineReadsIt(t *testing.T) {
	codebase := shop(t)
	program := csharp.Of(codebase)
	var want struct {
		Methods map[string]goldenMethod      `json:"methods"`
		Types   map[string]goldenDeclaration `json:"types"`
	}
	golden(t, "program", &want)
	tests := map[string]bool{}
	for _, file := range codebase.Files() {
		if file.Test {
			tests[strings.TrimPrefix(file.Path, fixture.root+"/")] = true
		}
	}
	methods := map[string]goldenMethod{}
	for _, match := range csharp.In(codebase).WhereMethodDeclaration().Get() {
		method := csharp.Node{Match: match}
		callers := want.Methods[method.Symbol()].Callers
		if parameters := method.Parameters(); len(parameters) == 0 || !parameters[0].HasModifier("this") {
			outside := 0
			for _, caller := range callers {
				if file, _, _ := strings.Cut(caller, "@"); !tests[file] {
					outside++
				}
			}
			if calls := program.CallsTo(method).OutsideTests; calls != outside {
				t.Errorf("%s: %d calls outside the tests, PHP's callers say %d", method.Symbol(), calls, outside)
			}
		}
		methods[method.Symbol()] = goldenMethod{HandedOut: program.IsHandedOut(method.Name()), Envied: method.EnviedParameter(program), Unpacks: method.UnpacksTargetFromContainerParam(program)}
	}
	// The callers are held to CallsTo above; what is compared here is what else the program answers of a method.
	for symbol, method := range want.Methods {
		method.Callers = nil
		want.Methods[symbol] = method
	}
	compare(t, "method", want.Methods, methods)
	types := map[string]goldenDeclaration{}
	for _, match := range csharp.In(codebase).WhereType().Get() {
		symbol := csharp.Node{Match: match}.Symbol()
		types[symbol] = goldenDeclaration{Declared: program.DeclaresType(symbol), Record: program.DeclaresRecord(symbol), Enum: program.EnumMembers(symbol), Cased: program.ComparesAsACase(symbol)}
	}
	compare(t, "declaration", want.Types, types)
}
