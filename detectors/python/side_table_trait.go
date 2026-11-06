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

func (SideTableTraitDetector) Sin() sins.Sin {
	return pysins.SideTableTrait{}
}

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

func byFile(matches []engine.Match) map[string][]engine.Match {
	grouped := map[string][]engine.Match{}

	for _, match := range matches {
		grouped[match.File()] = append(grouped[match.File()], match)
	}

	return grouped
}

func registeredNames(written []engine.Match) []string {
	var registered []string

	for _, assign := range written {
		if _, names := (py.Node{Match: assign}).InstanceNames(); len(names) > 0 {
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
