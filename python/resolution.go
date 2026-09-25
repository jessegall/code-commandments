package python

import (
	"slices"
	"strings"
)

// keyTypes is the scalar types that read as a lookup key: an identity, not a collaborator.
var keyTypes = []string{"str", "int"}

// UnpacksTargetFromContainerParam says whether the def resolves a key parameter against a container parameter,
// `node = workflow.graph.node(node_id)`, keeps the result, uses the container for nothing else, and does more with
// the result than hand it back: the container is only packaging.
func (p *Program) UnpacksTargetFromContainerParam(def Node) bool {
	var containers, keys []string
	for _, parameter := range def.Parameters() {
		annotation := parameter.Child("annotation")
		if p.isContainer(annotation) {
			containers = append(containers, parameter.Name())
		}
		if slices.Contains(keyTypes, annotation.DottedName()) {
			keys = append(keys, parameter.Name())
		}
	}
	if len(containers) == 0 || len(keys) == 0 {
		return false
	}
	for _, sole := range def.SoleAssignments() {
		container := resolvedAgainst(sole.Value, keys)
		if !slices.Contains(containers, container) || def.onlyResolves(sole.Local) {
			continue
		}
		if def.isOnlyPackaging(container, sole.Value) && def.isOnlyAKey(sole.Value) {
			return true
		}
	}

	return false
}

// isContainer says whether the annotation names a class the program declares that is no enum: an enum carries
// behaviour, and `rate.cents(grams)` is asking it, not digging in it.
func (p *Program) isContainer(annotation Node) bool {
	spelled := annotation.DottedName()

	return spelled != "" && p.DeclaresClass(spelled) && !p.IsEnum(spelled[strings.LastIndex(spelled, ".")+1:])
}

// resolvedAgainst is the name the lookup resolves one of the keys against, `workflow` in
// `workflow.graph.node(node_id)` and `workflow.nodes[node_id]`; empty when it is no single-key lookup. A call
// taking the key beside other arguments is a query, not a resolution.
func resolvedAgainst(lookup Node, keys []string) string {
	from, key := lookupParts(lookup)
	if !from.Exists() || key.Kind() != "Name" || !slices.Contains(keys, key.Name()) {
		return ""
	}

	return from.RootName()
}

// lookupParts is what a lookup resolves against and the key it resolves by: a method called with one argument, or
// a subscript.
func lookupParts(lookup Node) (Node, Node) {
	switch lookup.Kind() {
	case "Call":
		if lookup.Callee().Kind() == "Attribute" && len(lookup.Arguments())+len(lookup.Keywords()) == 1 {
			if len(lookup.Arguments()) == 1 {
				return lookup.Callee().Child("value"), lookup.Arguments()[0]
			}

			return lookup.Callee().Child("value"), lookup.Keywords()[0]
		}
	case "Subscript":
		return lookup.Child("value"), lookup.Child("slice")
	}

	return Node{}, Node{}
}

// onlyResolves says whether the def is the resolver itself: the lookup, guards raising the not-found failure, and
// the local handed back. That is where the rule sends the resolution.
func (n Node) onlyResolves(local string) bool {
	statements := n.StatementsBeyondText()
	if len(statements) < 2 {
		return false
	}
	last := statements[len(statements)-1]
	if last.Kind() != "Return" || last.Child("value").DottedName() != local {
		return false
	}

	return !slices.ContainsFunc(statements[1:len(statements)-1], func(between Node) bool {
		return between.Kind() != "If" || len(between.ChildrenIn("orelse")) > 0 || slices.ContainsFunc(between.ChildrenIn("body"), func(statement Node) bool {
			return statement.Kind() != "Raise"
		})
	})
}

// isOnlyAKey says whether the key the lookup resolves by is used for nothing else. A def that also reads the id
// itself, quoting it, storing it or handing it on, needs the id, and taking the resolved object would not do.
func (n Node) isOnlyAKey(lookup Node) bool {
	_, key := lookupParts(lookup)

	return !slices.ContainsFunc(n.ExpressionsIn(), func(read Node) bool {
		return read != key && read.Kind() == "Name" && read.Name() == key.Name()
	})
}

// isOnlyPackaging says whether the container is used for nothing but the lookup: every other mention a plain
// attribute read. Handed on whole, compared, or asked to do something, it is a co-subject the def needs.
func (n Node) isOnlyPackaging(container string, lookup Node) bool {
	inLookup := lookup.evaluatedIn()

	return !slices.ContainsFunc(n.ExpressionsIn(), func(read Node) bool {
		if read.Kind() != "Name" || read.Name() != container || slices.Contains(inLookup, read) {
			return false
		}
		around := read.wrapper()
		if around.Kind() != "Attribute" {
			return true
		}
		outer := around.wrapper()

		return outer.IsCall() && outer.Callee() == around
	})
}

// SoleAssignment is a local the def assigns once, to one name, and the value it assigns.
type SoleAssignment struct {
	Local string
	Value Node
}

// SoleAssignments is every local the def writes exactly once, by a plain `name = value`, with that value, in the
// order written.
func (n Node) SoleAssignments() []SoleAssignment {
	writes := map[string]int{}
	var assigned []SoleAssignment
	for _, statement := range n.statementsIn() {
		for _, written := range statement.writtenNames() {
			writes[written]++
		}
		targets := statement.ChildrenIn("targets")
		if statement.Kind() == "Assign" && len(targets) == 1 && targets[0].Kind() == "Name" {
			assigned = slices.DeleteFunc(assigned, func(sole SoleAssignment) bool { return sole.Local == targets[0].Name() })
			assigned = append(assigned, SoleAssignment{Local: targets[0].Name(), Value: statement.Child("value")})
		}
	}

	return slices.DeleteFunc(assigned, func(sole SoleAssignment) bool { return writes[sole.Local] != 1 })
}

// StatementsBeyondText is the def's body without its docstring, or any other string standing alone.
func (n Node) StatementsBeyondText() []Node {
	return slices.DeleteFunc(n.ChildrenIn("body"), func(statement Node) bool {
		_, isText := statement.Child("value").Text()

		return statement.Kind() == "Expr" && isText
	})
}
