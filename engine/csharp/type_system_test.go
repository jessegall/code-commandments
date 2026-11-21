package csharp_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

func TestInheritedContractsReachIndirectImplementers(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Providers.cs": `namespace Shop;
public interface Reader { string Read(); }
public class Base : Reader { public string Read() => "ready"; }
public sealed class Provider : Base {}
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

func TestCrossModuleDependencyUsesNamespaces(t *testing.T) {
	file := func(name, class, parameterType string) *contract.File {
		list := func(role string, nodes ...*contract.Node) contract.Parts {
			return contract.Parts{{Role: role, List: true, Nodes: nodes}}
		}
		declaration := &contract.Node{ID: 2, Kind: "type", Facts: &contract.Facts{Name: name, Symbol: class, Form: "class"}}
		if parameterType != "" {
			parameter := &contract.Node{ID: 4, Kind: "parameter", Resolved: &contract.Type{Kind: "named", Name: parameterType},
				Parts: contract.Parts{{Role: "binding", Nodes: []*contract.Node{{ID: 5, Kind: "binding", Facts: &contract.Facts{Name: "warehouse"}}}}}}
			constructor := &contract.Node{ID: 3, Kind: "function", Facts: &contract.Facts{Name: name, Form: "constructor"}, Parts: list("parameters", parameter)}
			declaration.Parts = list("writtenMembers", constructor)
		}
		module := &contract.Node{ID: 1, Kind: "module", Parts: list("declarations", declaration)}

		return &contract.File{Path: name + ".cs", Language: contract.CSharp, Root: &contract.Node{Kind: "file", Parts: list("modules", module)}}
	}
	const warehouse = "global::Shop.Warehouse.WarehouseClient"
	const status = "global::Shop.StatusStore"
	codebase := engine.Load(&contract.Stream{Header: contract.Header{Language: contract.CSharp}, Files: []*contract.File{
		file("WarehouseClient", warehouse, ""),
		file("StatusStore", status, ""),
		file("StatusPage", "global::Shop.StatusPage", status),
		file("ShopEntry", "global::Shop.ShopEntry", warehouse),
		file("CatalogPage", "global::Shop.Catalog.CatalogPage", warehouse),
	}})
	findings := codebase.WhereIs(engine.Parameter).
		Where(engine.Match.InjectsAnotherModulesConcreteService).Get()
	var constructors []string
	for _, finding := range findings {
		constructors = append(constructors, finding.DeclaringFunction().Name())
	}
	slices.Sort(constructors)
	if !slices.Equal(constructors, []string{"CatalogPage", "ShopEntry"}) {
		t.Fatalf("want the two dependencies on Warehouse, got %v", constructors)
	}
}
