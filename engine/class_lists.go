package engine

import (
	"path"
	"slices"
)

const leastListedClasses = 5

func (m Match) ConcreteClassReference() string {
	name := m.Named()
	if name != "" && slices.ContainsFunc(m.Codebase().Declarations(name), Match.IsConcreteClass) {
		return name
	}

	return ""
}

func (m Match) IsConcreteClass() bool {
	return m.TypeKind() == "class" && !m.HasModifier("abstract")
}

func (m Match) ListsEveryClassOfItsFolders() bool {
	read := m.grammar()
	if read.ListedClasses == nil {
		return false
	}
	listed := read.ListedClasses(m)
	if len(listed) < leastListedClasses {
		return false
	}
	types, typed := m.Types()
	if !typed {
		return false
	}
	folders := map[string]bool{}
	for _, class := range listed {
		declaration, _ := types.Declaration(class)
		folders[path.Dir(declaration.File())] = true
	}
	for _, shared := range sharedSupertypes(types, listed) {
		if everyClassOfTheTypeIsListed(types, shared, folders, listed) {
			return true
		}
	}

	return false
}

func sharedSupertypes(types TypeSystem, classes []string) []string {
	shared := Supertypes(types, classes[0])
	for _, class := range classes[1:] {
		supertypes := Supertypes(types, class)
		shared = slices.DeleteFunc(shared, func(each string) bool { return !slices.Contains(supertypes, each) })
	}

	return shared
}

func everyClassOfTheTypeIsListed(types TypeSystem, shared string, folders map[string]bool, listed []string) bool {
	for class, declaration := range types.Declarations() {
		if !declaration.IsConcreteClass() || !folders[path.Dir(declaration.File())] {
			continue
		}
		if IsA(types, class, shared) && !slices.Contains(listed, class) {
			return false
		}
	}

	return true
}
