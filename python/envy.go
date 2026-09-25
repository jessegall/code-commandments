package python

import (
	"slices"
	"strings"
)

// EnviedParameter is the parameter a method envies: one other object it was handed that it reaches through more
// than its own state, looping its collection or writing its fields, work that belongs on that object. None when
// it envies nothing. On the backend's signals and no names: a class filling in its base's contract is a
// polymorphic component, a method that builds a class is a mapper, and a loop that hands each element to one of
// the method's own collaborators is orchestration.
func (p *Program) EnviedParameter(method Node) (string, bool) {
	class, bound := method.BoundClass()
	if !bound || p.fillsAContract(class) || p.constructs(method) {
		return "", false
	}
	self := method.Parameters()[0].Name()
	reaches := method.AttributeReachesOn(append([]string{self}, p.OwnedParameters(method, class)...))
	own := reaches[self]
	delete(reaches, self)
	if len(reaches) != 1 {
		return "", false
	}
	var envied string
	for name, count := range reaches {
		if count <= own {
			return "", false
		}
		envied = name
	}
	mutates, iterates := method.mutates(envied), p.iterates(method, envied)
	if iterates && !mutates && method.delegatesElements(method.LoopsOver(envied), self) {
		return "", false
	}

	return envied, iterates || mutates
}

// BoundClass is the class an instance method or classmethod is bound to; none for a function, a staticmethod or
// a method that takes no parameter.
func (n Node) BoundClass() (Node, bool) {
	class := n.Parent()

	return class, n.IsMethod() && !n.IsStatic() && len(n.Parameters()) > 0
}

// fillsAContract says whether the class fills in a contract its base declares, overriding one of its methods,
// dunders aside: a polymorphic component whose behaviour is meant to act on other types.
func (p *Program) fillsAContract(class Node) bool {
	return slices.ContainsFunc(class.Methods(), func(member Node) bool { return !member.IsDunder() && p.IsOverride(member) })
}

// constructs says whether the method builds a class the program declares: a mapper or a factory, whose job is to
// read another object.
func (p *Program) constructs(method Node) bool {
	return slices.ContainsFunc(method.ExpressionsIn(), func(expression Node) bool {
		callee := expression.Callee().DottedName()

		return expression.IsCall() && callee != "" && p.DeclaresClass(callee)
	})
}

// iterates says whether the method loops over a collection of the name: `for line in order.lines`, or a
// comprehension over it.
func (p *Program) iterates(method Node, name string) bool {
	return len(method.LoopsOver(name)) > 0 || slices.ContainsFunc(method.ExpressionsIn(), func(expression Node) bool {
		return expression.Kind() == "comprehension" && expression.Child("iter").ProjectionRoot() == name
	})
}

// mutates says whether the method writes one of the name's attributes: `order.status = …`, `order.total += …`.
func (n Node) mutates(name string) bool {
	for _, statement := range n.statementsIn() {
		for _, target := range statement.writtenTargets() {
			if target.Kind() == "Attribute" && target.RootName() == name {
				return true
			}
		}
	}

	return false
}

// delegatesElements says whether each loop hands its element to one of self's own collaborators,
// `self.printer.print(line)`: the orchestrator doing its own job. Calling the method the loops are in again is
// recursion, which belongs where the collection is.
func (n Node) delegatesElements(loops []Node, self string) bool {
	return len(loops) > 0 && !slices.ContainsFunc(loops, func(loop Node) bool {
		return !slices.ContainsFunc(loop.bodyExpressions(), func(call Node) bool {
			callee := call.Callee()

			return call.IsCall() && callee.Kind() == "Attribute" && callee.RootName() == self && callee.DottedName() != self+"."+n.Name() &&
				slices.ContainsFunc(call.Arguments(), func(argument Node) bool { return argument.DottedName() == loop.Child("target").DottedName() })
		})
	})
}

// bodyExpressions is every evaluated expression inside a loop's body.
func (n Node) bodyExpressions() []Node {
	var within []Node
	for _, statement := range n.ChildrenIn("body") {
		within = append(within, statement.evaluatedIn()...)
	}

	return within
}

// AttributeReachesOn is how many times the def reaches an attribute through each of the names: `order.total`
// counts once for `order`.
func (n Node) AttributeReachesOn(names []string) map[string]int {
	reaches := map[string]int{}
	for _, expression := range n.ExpressionsWithin() {
		if owner := expression.Child("value"); expression.Kind() == "Attribute" && owner.Kind() == "Name" && slices.Contains(names, owner.Name()) {
			reaches[owner.Name()]++
		}
	}

	return reaches
}

// LoopsOver is every for loop in the def whose iterable projects from the name: `for line in order.lines`.
func (n Node) LoopsOver(name string) []Node {
	var loops []Node
	for _, statement := range n.statementsIn() {
		if (statement.Kind() == "For" || statement.Kind() == "AsyncFor") && statement.Child("iter").ProjectionRoot() == name {
			loops = append(loops, statement)
		}
	}

	return loops
}

// ProjectionRoot is the name an attribute chain projects from, read through calls that take no argument:
// `order` for `order.lines` and `order.lines.items()`; empty for anything else.
func (n Node) ProjectionRoot() string {
	switch n.Kind() {
	case "Attribute":
		if owner := n.Child("value"); owner.Kind() == "Name" {
			return owner.Name()
		}

		return n.Child("value").ProjectionRoot()
	case "Call":
		if len(n.Arguments()) == 0 && len(n.Keywords()) == 0 && n.Callee().Kind() == "Attribute" {
			return n.Callee().ProjectionRoot()
		}
	}

	return ""
}

// RootName is the name an attribute, index or call chain starts from: `order` for `order.lines[0].total()`.
func (n Node) RootName() string {
	switch n.Kind() {
	case "Name":
		return n.Name()
	case "Attribute", "Subscript":
		return n.Child("value").RootName()
	case "Call":
		return n.Callee().RootName()
	}

	return ""
}

// ExpressionsIn is every expression the node evaluates, its own decorators included.
func (n Node) ExpressionsIn() []Node {
	return n.evaluatedIn()
}

// ExpressionsWithin is every expression a def's parameters and body, or a class's body, evaluate, nested
// definitions included, the node's own head aside: its decorators, and a class's bases and keywords.
func (n Node) ExpressionsWithin() []Node {
	var within []Node
	for _, child := range n.Children() {
		if !slices.Contains([]string{"decorator_list", "bases", "keywords"}, child.Node().Field) {
			within = append(within, child.evaluatedIn()...)
		}
	}

	return within
}

// evaluatedIn is the node, when it is an evaluated expression, and every evaluated expression below it.
func (n Node) evaluatedIn() []Node {
	var evaluated []Node
	for _, node := range append([]Node{n}, n.Descendants()...) {
		if node.IsEvaluated() || node.Kind() == "comprehension" {
			evaluated = append(evaluated, node)
		}
	}

	return evaluated
}

// factTypes is the types a fact comes back as: a plain answer, not an object to go on working with.
var factTypes = []string{"bool", "int", "float", "str", "list", "dict", "set", "tuple", "frozenset"}

// IsLookupEnvious says whether the method uses its one owned parameter's identity as a key to fetch a fact about
// it through one of its own collaborators, `self.registry.get(node.key).reserved`: the object is treated as a key
// into its own data, so the fact belongs on it.
func (p *Program) IsLookupEnvious(method Node) bool {
	class, bound := method.BoundClass()
	if !bound || !slices.Contains(factTypes, method.ReturnedTypeName()) {
		return false
	}
	self := method.Parameters()[0].Name()
	owned := p.OwnedParameters(method, class)
	if len(owned) != 1 || !method.usedOnlyThroughAttributes(owned[0]) {
		return false
	}

	return slices.ContainsFunc(method.ReturnedValues(), func(returned Node) bool {
		return slices.ContainsFunc(returned.evaluatedIn(), func(read Node) bool { return p.readsAFetchKeyedBy(read, self, owned[0]) })
	})
}

// ReturnedTypeName is the dotted name the def's written return type spells, a subscript's own: `list` for
// `list[int]`; empty when it writes none.
func (n Node) ReturnedTypeName() string {
	returns := n.Child("returns")
	if returns.Kind() == "Subscript" {
		return returns.Child("value").DottedName()
	}

	return returns.DottedName()
}

// usedOnlyThroughAttributes says whether every mention of the name in the method is an attribute read off it.
// Handed on whole, an argument or a return, it is a subject the method works with, not a key.
func (n Node) usedOnlyThroughAttributes(name string) bool {
	mentioned := false
	for _, read := range n.ExpressionsIn() {
		if read.Kind() != "Name" || read.Name() != name {
			continue
		}
		mentioned = true
		if around := read.wrapper(); around.Kind() != "Attribute" || around.Child("value") != read {
			return false
		}
	}

	return mentioned
}

// readsAFetchKeyedBy says whether the read is an attribute of a fetch made on one of self's collaborators,
// `self.registry.get(…)` or `self.specs[…]`, keyed by an attribute of the name that is no enum. Keyed by an enum
// field, the fetch picks a strategy for a whole family of objects, which is dispatch.
func (p *Program) readsAFetchKeyedBy(read Node, self, name string) bool {
	if read.Kind() != "Attribute" {
		return false
	}
	fetch := read.Child("value")
	var store Node
	var keys []Node
	switch fetch.Kind() {
	case "Call":
		if fetch.Callee().Kind() == "Attribute" {
			store = fetch.Callee().Child("value")
		}
		keys = append(fetch.Arguments(), fetch.Keywords()...)
	case "Subscript":
		store, keys = fetch.Child("value"), []Node{fetch.Child("slice")}
	}
	if store.Kind() != "Attribute" || store.RootName() != self {
		return false
	}
	var reads []Node
	for _, key := range keys {
		for _, part := range append([]Node{key}, key.Descendants()...) {
			if owner := part.Child("value"); part.Kind() == "Attribute" && owner.Kind() == "Name" && owner.Name() == name {
				reads = append(reads, part)
			}
		}
	}

	return len(reads) > 0 && slices.ContainsFunc(reads, func(key Node) bool { return !p.isEnumField(key) })
}

// isEnumField says whether mypy types the key as a member of an enum the program declares.
func (p *Program) isEnumField(key Node) bool {
	resolved := key.Node().Resolved
	if resolved == nil || resolved.Kind != "named" {
		return false
	}

	return p.IsEnum(resolved.Name[strings.LastIndex(resolved.Name, ".")+1:])
}
