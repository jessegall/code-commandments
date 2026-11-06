package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	sin "github.com/jessegall/code-commandments/sins/csharp"
)

func init() {
	detectors.Register(catalog.CSharp, SideTableTraitDetector{})
}

// SideTableTraitDetector finds a trait of a type's registered instances kept in a collection of their names.
type SideTableTraitDetector struct{}

func (SideTableTraitDetector) Sin() sins.Sin {
	return sin.SideTableTrait{}
}

func (SideTableTraitDetector) Find(codebase *engine.Codebase) []engine.Match {
	fields := byFile(csharp.In(codebase).
		WhereKind("VariableDeclarator").
		Where(engine.As(csharp.Node.IsFieldDeclarator)).
		Get())
	asked := byFile(csharp.In(codebase).WhereKind("InvocationExpression").Get())

	var found []engine.Match

	for file, declared := range fields {
		registered := registeredNames(declared)
		if len(registered) == 0 {
			continue
		}

		for _, field := range declared {
			members := (csharp.Node{Match: field}).StringMembers()

			if len(members) == 0 || !namesInstances(members, registered) {
				continue
			}

			if asksAboutOne(asked[file], field.Name()) {
				found = append(found, field)
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

	for _, field := range declared {
		if _, names := (csharp.Node{Match: field}).InstanceNames(); len(names) > 0 {
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

func asksAboutOne(calls []engine.Match, field string) bool {
	if field == "" {
		return false
	}

	for _, call := range calls {
		if (csharp.Node{Match: call}).AsksAboutOne(field) {
			return true
		}
	}

	return false
}
