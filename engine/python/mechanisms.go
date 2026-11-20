package python

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func mechanismFacts(codebase *engine.Codebase) engine.MechanismFacts {
	program := Of(codebase)
	population := program.FunctionReach()
	reach := map[string]map[string]bool{}
	for _, module := range program.Modules() {
		for _, function := range module.Nodes() {
			if !function.IsFunction() {
				continue
			}
			class := function.EnclosingClass()
			if !class.Exists() {
				continue
			}
			symbol := class.Identity()
			if reach[symbol] == nil {
				reach[symbol] = map[string]bool{}
			}
			for resource := range population.Of(DeclarationOf(function)) {
				reach[symbol][strings.TrimPrefix(resource, typeResource)] = true
			}
		}
	}

	return engine.MechanismFacts{
		Classes: engine.Counting(reach),
		IsType:  IsTypeResource,
		IsTerminal: func(resource string) bool {
			return len(codebase.Declarations(strings.TrimPrefix(resource, functionResource))) == 0
		},
		Namespace: engine.Match.Namespace,
	}
}
