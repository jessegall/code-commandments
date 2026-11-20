package golang

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func interfaceSignatures(declaration engine.Match) []string {
	extra := declaration.Node().Extras
	if extra == nil || extra.Go == nil || declaration.Child("typeParams").Exists() ||
		slices.ContainsFunc(extra.Go.Methods, func(signature string) bool { return strings.Contains(signature, "invalid type") }) ||
		slices.ContainsFunc(declaration.Members(), func(field engine.Match) bool {
			return len(field.ChildrenIn("names")) == 0
		}) {
		return nil
	}
	return extra.Go.Methods
}

func interfaceVisible(target, from engine.Match) bool {
	source, destination := from.Namespace(), target.Namespace()
	if source == destination {
		return true
	}
	if !(Node{Match: target}).IsExported() ||
		slices.ContainsFunc(target.Members(), func(member engine.Match) bool { return !(Node{Match: member}).IsExported() }) {
		return false
	}
	parent, internal := strings.CutSuffix(destination, "/internal")
	if at := strings.LastIndex(destination, "/internal/"); !internal && at >= 0 {
		parent, internal = destination[:at], true
	}

	return !internal || source == parent || strings.HasPrefix(source, parent+"/")
}

// implementedTypes adds the contracts satisfied by Go method sets to the declared types.
type implementedTypes struct {
	engine.TypeSystem
	contracts map[string][]string
}

func (types implementedTypes) Contracts(symbol string) []string {
	return types.contracts[symbol]
}

func typeKind(declaration engine.Match) string {
	switch declaration.Child("type").Kind() {
	case "StructType":
		return "struct"
	case "InterfaceType":
		return "interface"
	}

	return "named"
}

func typeSystem(codebase *engine.Codebase) engine.TypeSystem {
	return engine.Analysis(codebase.Of(contract.Go), "go-implemented-types", readTypeSystem)
}

func readTypeSystem(codebase *engine.Codebase) engine.TypeSystem {
	declared := engine.TypesFromDeclarations(codebase)
	methods := map[string]map[string]bool{}
	owners := map[string][]string{}
	var interfaces []string
	for symbol, declaration := range declared.Declarations() {
		extra := declaration.Node().Extras
		if extra == nil || extra.Go == nil || len(extra.Go.Methods) == 0 {
			continue
		}
		set := map[string]bool{}
		for _, method := range extra.Go.Methods {
			set[method] = true
			owners[method] = append(owners[method], symbol)
		}
		methods[symbol] = set
		if declaration.TypeKind() == "interface" {
			interfaces = append(interfaces, symbol)
		}
	}
	slices.Sort(interfaces)
	contracts := map[string][]string{}
	for _, iface := range interfaces {
		needed := methods[iface]
		for _, candidate := range nearestMethodOwners(owners, needed) {
			if candidate != iface && hasMethods(methods[candidate], needed) {
				contracts[candidate] = append(contracts[candidate], iface)
			}
		}
	}

	return implementedTypes{TypeSystem: declared, contracts: contracts}
}

func nearestMethodOwners(owners map[string][]string, required map[string]bool) []string {
	var fewest []string
	for method := range required {
		if found := owners[method]; fewest == nil || len(found) < len(fewest) {
			fewest = found
		}
	}

	return fewest
}

func hasMethods(methods, required map[string]bool) bool {
	for method := range required {
		if !methods[method] {
			return false
		}
	}

	return true
}
