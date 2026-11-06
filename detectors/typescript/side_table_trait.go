package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/sins"
	sin "github.com/jessegall/code-commandments/sins/typescript"
)

func init() {
	detectors.Register(catalog.TypeScript, SideTableTraitDetector{})
}

// SideTableTraitDetector finds a trait of a module's registered objects kept in a collection of their names.
type SideTableTraitDetector struct{}

func (SideTableTraitDetector) Sin() sins.Sin {
	return sin.SideTableTrait{}
}

func (SideTableTraitDetector) Find(codebase *engine.Codebase) []engine.Match {
	declarations := byFile(typescript.In(codebase).
		WhereKind("VariableDeclaration").
		Where(engine.As(typescript.Node.IsModuleLevelDeclaration)).
		Get())
	asked := byFile(typescript.In(codebase).WhereKind("CallExpression").Get())

	var found []engine.Match

	for file, declared := range declarations {
		registered := registeredNames(declared)
		if len(registered) == 0 {
			continue
		}

		for _, declaration := range declared {
			members := (typescript.Node{Match: declaration}).StringMembers()

			if len(members) == 0 || !namesInstances(members, registered) {
				continue
			}

			if asksAboutOne(asked[file], declaration.Name()) {
				found = append(found, declaration)
			}
		}
	}

	return found
}

func byFile(matches []engine.Match) map[string][]engine.Match {
	grouped := map[string][]engine.Match{}

	for _, match := range matches {
		grouped[match.File()] = append(grouped[match.File()], match)
	}

	return grouped
}

func registeredNames(declared []engine.Match) []string {
	var registered []string

	for _, declaration := range declared {
		for _, names := range (typescript.Node{Match: declaration}).KeyedInstanceNames() {
			registered = append(registered, names...)
		}
	}

	return registered
}

func namesInstances(members, registered []string) bool {
	for _, member := range members {
		if !slices.Contains(registered, member) {
			return false
		}
	}

	return true
}

func asksAboutOne(calls []engine.Match, held string) bool {
	if held == "" {
		return false
	}

	for _, call := range calls {
		if (typescript.Node{Match: call}).AsksAboutOne(held) {
			return true
		}
	}

	return false
}
