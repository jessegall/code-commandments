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

// Sin is the sin the detector finds.
func (SideTableTraitDetector) Sin() sins.Sin {
	return sin.SideTableTrait{}
}

// Unpublished keeps it out of every catalog while its hits are being read on real code.
func (SideTableTraitDetector) Unpublished() {}

// Find is every field holding a collection of names that are the names its own file's registry gives its
// instances, and that the file then reaches for them through. The three together tell a side table from an
// ordinary collection of words: the names are the registry's, there is more than one instance to be a type's,
// and the file uses them as the handle rather than holding a vocabulary that happens to look alike.
func (SideTableTraitDetector) Find(codebase *engine.Codebase) []engine.Match {
	fields := byFile(csharp.In(codebase).
		WhereKind("VariableDeclarator").
		Where(engine.As(csharp.Node.IsFieldDeclarator)).
		Get())
	mentions := byFile(csharp.In(codebase).WhereKind("IdentifierName").Get())

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

			if handled(mentions[file], field.Name()) {
				found = append(found, field)
			}
		}
	}

	return found
}

// byFile groups matches by the file they stand in, so a file is read against itself alone.
func byFile(matches []engine.Match) map[string][]engine.Match {
	grouped := map[string][]engine.Match{}

	for _, match := range matches {
		grouped[match.File()] = append(grouped[match.File()], match)
	}

	return grouped
}

// registeredNames is every name the file's own registries give the instances they hold.
func registeredNames(declared []engine.Match) []string {
	var registered []string

	for _, field := range declared {
		if _, names := (csharp.Node{Match: field}).InstanceNames(); len(names) > 0 {
			registered = append(registered, names...)
		}
	}

	return registered
}

// namesInstances says whether every member of the collection names a registered instance: one stray member and
// it is a vocabulary of its own, not a table about the type.
func namesInstances(members, registered []string) bool {
	for _, member := range members {
		if !slices.Contains(registered, member) {
			return false
		}
	}

	return true
}

// handled says whether the file reaches for its instances through the collection, by naming it somewhere other
// than where it is declared. Holding the names is not the sin; using them as the handle is.
func handled(mentions []engine.Match, field string) bool {
	if field == "" {
		return false
	}

	for _, mention := range mentions {
		if mention.Name() == field {
			return true
		}
	}

	return false
}
