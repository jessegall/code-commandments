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
		declaration := &contract.Node{ID: 1, Kind: "ClassDeclaration", Field: "Members", Name: name,
			Is: []string{"type-declaration"}, Facts: &contract.Facts{Symbol: class}}
		if parameterType != "" {
			parameter := &contract.Node{ID: 4, Kind: "Parameter", Is: []string{"parameter"}, Field: "Parameters", Name: "warehouse",
				Resolved: &contract.Type{Kind: "named", Name: parameterType}}
			list := &contract.Node{ID: 3, Kind: "ParameterList", Field: "ParameterList", Children: []*contract.Node{parameter}}
			declaration.Children = []*contract.Node{{ID: 2, Kind: "ConstructorDeclaration", Is: []string{"function"}, Name: name, Field: "Members",
				Children: []*contract.Node{list}}}
		}
		return &contract.File{Path: name + ".cs", Language: contract.CSharp,
			Root: &contract.Node{Kind: "CompilationUnit", Children: []*contract.Node{declaration}}}
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
