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
