package golang_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestTheGoTypeSystemUsesMethodSignaturesAndPointerReceivers(t *testing.T) {
	codebase := codebaseOf(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"reader.go": `package shop

type Reader interface { Read(available int) (data string, err error) }
type Provider struct{}
func (*Provider) Read(size int) (string, error) { return "", nil }
type Other struct{}
func (Other) Read(size string) (string, error) { return "", nil }
`,
	})
	reader := codebase.WhereTypeDeclaration().Where(func(declaration engine.Match) bool { return declaration.Name() == "Reader" }).First()
	types, available := reader.Types()
	if !available {
		t.Fatal("no Go type system for Reader")
	}
	implementers := engine.Implementers(types, reader.Identity())
	if len(implementers) != 1 || implementers[0].Name() != "Provider" {
		t.Fatalf("expected only Provider to implement Reader, found %v", implementers)
	}
	if !slices.Contains(types.Contracts(implementers[0].Identity()), reader.Identity()) {
		t.Error("Provider should record its Reader contract")
	}
}

func TestCrossModuleStructValuesAndTranslations(t *testing.T) {
	codebase := codebaseOf(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"inputs/state.go": `package inputs
type State struct { Count int; Label string; Items []string }
type Service struct { private int }
`,
		"outputs/report.go": `package outputs
type Report struct { Count int; Label string; Items []string }
`,
		"adapter/adapter.go": `package adapter
import ("example.com/shop/inputs"; "example.com/shop/outputs")
type Adapter struct{}
func NewAdapter(settings inputs.State, service inputs.Service) *Adapter { return &Adapter{} }
func Translate(s inputs.State) *outputs.Report {
    report := &outputs.Report{Count: s.Count, Label: s.Label}
    for _, x := range s.Items { report.Items = append(report.Items, x) }
    return report
}
func Summarize(s inputs.State) string {
    for _, x := range s.Items { if s.Count > 0 { return s.Label + x } }
    return ""
}
`,
		"adapter/adapter_test.go": `package adapter
import "example.com/shop/inputs"
func assertion(s inputs.State) string {
    for _, x := range s.Items { if s.Count > 0 { return s.Label + x } }
    return ""
}
`,
	})
	params := funcNamed(t, codebase, "NewAdapter").Parameters()
	if params[0].InjectsAnotherModulesConcreteService() || !params[1].InjectsAnotherModulesConcreteService() {
		t.Fatal("public value fields and private service state should be distinguished")
	}
	if funcNamed(t, codebase, "Translate").EnviesAnotherObject() || !funcNamed(t, codebase, "Summarize").EnviesAnotherObject() {
		t.Fatal("a cross-module translation and a summary should be distinguished")
	}
	if funcNamed(t, codebase, "assertion").EnviesAnotherObject() {
		t.Fatal("a Go test helper may inspect the result it asserts on")
	}
}

func TestAMapOfNamedValuesIsNotAReturnBag(t *testing.T) {
	codebase := codebaseOf(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"settings.go": `package shop
type Value struct { ready bool }
func table() map[string]Value { return map[string]Value{"one": {}, "two": {}} }
func loose() map[string]any { return map[string]any{"name": "one", "ready": true} }
`,
	})
	if found := codebase.WhereIs(engine.Return).Where(engine.Match.ReturnsRecordBag).Get(); len(found) != 1 || found[0].EnclosingFunction().Name() != "loose" {
		t.Fatalf("only the loose map should be reported, found %v", found)
	}
}
