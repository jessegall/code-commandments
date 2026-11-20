package python_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestInheritedContractsReachIndirectImplementers(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{"providers.py": `from typing import Protocol

class Reader(Protocol):
    def read(self) -> str: ...

class Base(Reader):
    def read(self) -> str:
        return "ready"

class Provider(Base):
    pass
`})
	reader := codebase.WhereTypeDeclaration().Where(func(node engine.Match) bool { return node.Name() == "Reader" }).First()
	provider := codebase.WhereTypeDeclaration().Where(func(node engine.Match) bool { return node.Name() == "Provider" }).First()
	if !reader.Exists() || !provider.Exists() {
		t.Fatal("both declarations must be present")
	}
	types, available := provider.Types()
	if !available || !engine.IsA(types, provider.Identity(), reader.Identity()) {
		t.Fatal("Provider should inherit the Reader contract from its base")
	}
	if !slices.ContainsFunc(engine.Implementers(types, reader.Identity()), func(node engine.Match) bool { return node.Identity() == provider.Identity() }) {
		t.Error("Provider should be listed as an indirect Reader implementer")
	}
}
