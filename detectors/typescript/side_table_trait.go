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

// Sin is the sin the detector finds.
func (SideTableTraitDetector) Sin() sins.Sin {
	return sin.SideTableTrait{}
}

// Unpublished keeps it out of every catalog while its hits are being read on real code.
func (SideTableTraitDetector) Unpublished() {}

// Find is every module-level collection of names that are the names its own module's registry gives its objects,
// and that the module then uses as a handle on them. The three together tell a side table from an ordinary list
// of words: the names are the registry's, there is more than one object to be a type's, and the module reaches
// for the objects through them rather than holding a vocabulary that happens to look alike.
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

// byFile groups matches by the file they stand in, so a module is read against itself alone.
func byFile(matches []engine.Match) map[string][]engine.Match {
	grouped := map[string][]engine.Match{}

	for _, match := range matches {
		grouped[match.File()] = append(grouped[match.File()], match)
	}

	return grouped
}

// registeredNames is every name the module's own registries give the objects they hold, under whichever property
// carries it.
func registeredNames(declared []engine.Match) []string {
	var registered []string

	for _, declaration := range declared {
		for _, names := range (typescript.Node{Match: declaration}).KeyedInstanceNames() {
			registered = append(registered, names...)
		}
	}

	return registered
}

// namesInstances says whether every member of the collection names a registered object: one stray member and it
// is a vocabulary of its own, not a table about them.
func namesInstances(members, registered []string) bool {
	for _, member := range members {
		if !slices.Contains(registered, member) {
			return false
		}
	}

	return true
}

// asksAboutOne says whether the module asks the collection whether it holds one object — the question a trait
// answers, and the only use of it that a caller's own choice of what to act on cannot also look like.
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
