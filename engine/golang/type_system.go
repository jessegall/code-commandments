package golang

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

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
