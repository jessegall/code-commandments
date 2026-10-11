package python

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// SideTableTraitDetector finds a trait of a type kept in a collection of its instances' names beside the registry.
type SideTableTraitDetector struct{}

func init() {
	detectors.Register(catalog.Python, SideTableTraitDetector{})
}

// Sin is the sin the detector finds.
func (SideTableTraitDetector) Sin() sins.Sin {
	return pysins.SideTableTrait{}
}

// Find is every module-level collection of names that are the names of the instances its own module registers,
// and that the module then uses as a handle on them. The three together are what tells a side table from an
// ordinary lookup: the names are a type's, there is more than one of them to be a type's, and the module reaches
// for the instances through them rather than merely holding a vocabulary that happens to look alike.
func (SideTableTraitDetector) Find(codebase *engine.Codebase) []engine.Match {
	assigns := byFile(py.In(codebase).WhereAssign().Where(engine.As(py.Node.IsModuleLevelAssign)).Get())
	tested := byFile(py.In(codebase).WhereKind("Compare").Get())

	var found []engine.Match

	for file, written := range assigns {
		registered := registeredNames(written)
		if len(registered) == 0 {
			continue
		}

		for _, assign := range written {
			table := py.Node{Match: assign}
			members := table.StringMembers()

			if len(members) == 0 || !namesInstances(members, registered) {
				continue
			}

			if asked(tested[file], table.AssignedName()) {
				found = append(found, assign)
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

// registeredNames is every name the module's own registries give their instances.
func registeredNames(written []engine.Match) []string {
	var registered []string

	for _, assign := range written {
		if _, names := (py.Node{Match: assign}).InstanceNames(); len(names) > 0 {
			registered = append(registered, names...)
		}
	}

	return registered
}

// namesInstances says whether every member of the collection is the name of a registered instance: one stray
// member and it is a vocabulary of its own, not a table about the type.
func namesInstances(members, registered []string) bool {
	for _, member := range members {
		if !slices.Contains(registered, member) {
			return false
		}
	}

	return true
}

// asked says whether the module asks the collection about one instance — `x in QUIET` — which is the question a
// trait answers. Iterating it is deliberately not enough: a loop that acts on each member is a caller choosing
// what to act on, and nothing in the tree tells that from a trait. A set a module merely holds, or walks, says
// nothing about any one instance; a set it asks about one does.
func asked(reaches []engine.Match, table string) bool {
	if table == "" {
		return false
	}

	for _, node := range reaches {
		for _, child := range node.Children() {
			if child.Kind() == "Name" && child.Name() == table {
				return true
			}
		}
	}

	return false
}
