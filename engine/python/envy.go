package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

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

func (n Node) BoundClass() (Node, bool) {
	class := n.Parent()

	return class, n.IsMethod() && !n.IsStatic() && len(n.Parameters()) > 0
}

func (p *Program) fillsAContract(class Node) bool {
	return slices.ContainsFunc(class.Methods(), func(member Node) bool { return !member.IsDunder() && p.IsOverride(member) })
}

func (p *Program) constructs(method Node) bool {
	return slices.ContainsFunc(method.ExpressionsIn(), func(expression Node) bool {
		callee := expression.Callee().DottedName()

		return expression.IsCall() && callee != "" && p.DeclaresClass(callee)
	})
}

func (p *Program) iterates(method Node, name string) bool {
	return len(method.LoopsOver(name)) > 0 || slices.ContainsFunc(method.ExpressionsIn(), func(expression Node) bool {
		return expression.Kind() == "comprehension" && expression.Child("iter").ProjectionRoot() == name
	})
}

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

func (n Node) delegatesElements(loops []Node, _ string) bool {
	if len(loops) == 0 {
		return false
	}

	return !slices.ContainsFunc(loops, func(loop Node) bool { return !engine.PassesItsElementOn(loop.Match) })
}

func (n Node) bodyExpressions() []Node {
	var within []Node
	for _, statement := range n.ChildrenIn("body") {
		within = append(within, statement.evaluatedIn()...)
	}

	return within
}

func (n Node) AttributeReachesOn(names []string) map[string]int {
	reaches := map[string]int{}
	for _, expression := range n.ExpressionsWithin() {
		if owner := expression.Child("value"); expression.Kind() == "Attribute" && owner.Kind() == "Name" && slices.Contains(names, owner.Name()) {
			reaches[owner.Name()]++
		}
	}

	return reaches
}

func (n Node) LoopsOver(name string) []Node {
	var loops []Node
	for _, statement := range n.statementsIn() {
		if (statement.Kind() == "For" || statement.Kind() == "AsyncFor") && statement.Child("iter").ProjectionRoot() == name {
			loops = append(loops, statement)
		}
	}

	return loops
}

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

func (n Node) ExpressionsIn() []Node {
	return n.evaluatedIn()
}

func (n Node) ExpressionsWithin() []Node {
	var within []Node
	for _, child := range n.Children() {
		if !slices.Contains([]string{"decorator_list", "bases", "keywords"}, child.Node().Field) {
			within = append(within, child.evaluatedIn()...)
		}
	}

	return within
}

func (n Node) evaluatedIn() []Node {
	var evaluated []Node
	for _, node := range append([]Node{n}, n.Descendants()...) {
		if node.IsEvaluated() || node.Kind() == "comprehension" {
			evaluated = append(evaluated, node)
		}
	}

	return evaluated
}

var factTypes = []string{"bool", "int", "float", "str", "list", "dict", "set", "tuple", "frozenset"}

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

func (n Node) ReturnedTypeName() string {
	returns := n.Child("returns")
	if returns.Kind() == "Subscript" {
		return returns.Child("value").DottedName()
	}

	return returns.DottedName()
}

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

func (p *Program) isEnumField(key Node) bool {
	resolved := key.Node().Resolved
	if resolved == nil || resolved.Kind != "named" {
		return false
	}

	return p.IsEnumSymbol(resolved.Name)
}
