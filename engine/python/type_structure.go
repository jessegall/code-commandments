package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func selfReceiver(read engine.Match) bool {
	if !read.Is(engine.Identifier) && !read.Is(engine.Parameter) {
		return false
	}
	function := read.EnclosingFunction()
	parameters := function.Parameters()
	return len(parameters) > 0 && parameters[0].IsImplicit() && read.Name() == parameters[0].Name()
}

func privateMember(member engine.Match) bool {
	name := member.DeclaredName()
	return member.EnclosingType().Exists() && strings.HasPrefix(name, "_") && !strings.HasPrefix(name, "__")
}

func ownsSignature(function engine.Match) bool {
	owner := function.EnclosingType()
	return !owner.Exists() || function.IsAnnotated("final") || owner.IsAnnotated("final") ||
		strings.HasPrefix(function.Name(), "__")
}

func hasDynamicMembers(declaration engine.Match) bool {
	return slices.ContainsFunc(declaration.Members(), func(member engine.Match) bool {
		return member.Name() == "__getattr__" || member.Name() == "__getattribute__"
	})
}
