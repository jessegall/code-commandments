package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsModuleLevelDeclaration() bool {
	return n.Kind() == "VariableDeclaration" && !n.EnclosingFunction().Exists()
}

func (n Node) declared() Node {
	for _, child := range n.Children() {
		if child.Kind() == "ArrayLiteralExpression" {
			return Node{Match: child}
		}

		if child.Kind() == "NewExpression" {
			for _, held := range child.Children() {
				if held.Kind() == "ArrayLiteralExpression" {
					return Node{Match: held}
				}
			}
		}
	}

	return Node{}
}

func (n Node) StringMembers() []string {
	var names []string

	for _, member := range n.declared().Children() {
		text, literal := member.Text()
		if member.Kind() != "StringLiteral" || !literal || text == "" {
			return nil
		}
		names = append(names, text)
	}

	return names
}

func (n Node) KeyedInstanceNames() map[string][]string {
	objects := n.declared().Children()
	if len(objects) < 2 {
		return nil
	}

	keyed := map[string][]string{}
	for _, object := range objects {
		if object.Kind() != "ObjectLiteralExpression" {
			return nil
		}

		for _, property := range object.Children() {
			value := property.Child("initializer")
			text, literal := value.Text()

			if property.Kind() != "PropertyAssignment" || !literal || text == "" {
				continue
			}
			keyed[property.Name()] = append(keyed[property.Name()], text)
		}
	}

	for key, values := range keyed {
		if len(values) != len(objects) {
			delete(keyed, key)
		}
	}

	return keyed
}

var membershipQuestions = []string{"has", "includes", "contains"}

func (n Node) AsksAboutOne(held string) bool {
	if n.Kind() != "CallExpression" {
		return false
	}

	for _, child := range n.Children() {
		if child.Kind() != "PropertyAccessExpression" {
			continue
		}

		asked := false
		for _, question := range membershipQuestions {
			asked = asked || child.Name() == question
		}

		for _, named := range child.Children() {
			if asked && named.Kind() == "Identifier" && named.Name() == held {
				return true
			}
		}
	}

	return false
}

var walkMutators = []string{"pop", "shift", "unshift", "splice", "sort", "reverse", "fill", "copyWithin"}

func (n Node) ChangesTheCollectionItsLoopWalks() bool {
	changed, mutates := n.mutatedCollection()
	if !mutates {
		return false
	}
	if loop, walks := n.loopWalking(changed); walks {
		return !n.leavesThatLoopAtOnce(loop)
	}

	return n.insideEachOver(changed)
}

func (n Node) mutatedCollection() (Node, bool) {
	callee := n.Child("expression")
	if n.Kind() != "CallExpression" || callee.Kind() != "PropertyAccessExpression" {
		return Node{}, false
	}
	if !slices.Contains(walkMutators, callee.Child("name").Written()) {
		return Node{}, false
	}

	return Node{callee.Child("expression")}, true
}

func (n Node) loopWalking(collection Node) (Node, bool) {
	for held := n.Match; held.Exists(); held = held.Parent() {
		loop := held.Parent()
		if !loop.Is(engine.Loop) || loop.Kind() != "ForOfStatement" || held.Node().Field != "statement" {
			continue
		}
		if walked := loop.Child("expression"); walked.SameSyntax(collection.Match) {
			return Node{loop}, true
		}
	}

	return Node{}, false
}

func (n Node) insideEachOver(collection Node) bool {
	for held := n.Match; held.Exists(); held = held.Parent() {
		each := Node{held.Parent()}
		if each.Kind() != "CallExpression" || held.Node().Field != "arguments" {
			continue
		}
		callee := each.Child("expression")
		if callee.Kind() != "PropertyAccessExpression" || callee.Child("name").Written() != "forEach" {
			continue
		}
		receiver := Node{callee.Child("expression")}
		if receiver.SameSyntax(collection.Match) {
			return true
		}
	}

	return false
}

func (n Node) leavesThatLoopAtOnce(loop Node) bool {
	block := n.blockHolding()
	if len(block) == 0 {
		return false
	}

	return block[len(block)-1].leaves(loop)
}

func (n Node) blockHolding() []Node {
	for held := n.Match; held.Exists(); held = held.Parent() {
		if !held.Parent().Is(engine.Block) {
			continue
		}
		var statements []Node
		for _, each := range held.Parent().Children() {
			statements = append(statements, Node{each})
		}

		return statements
	}

	return nil
}

func (n Node) leaves(loop Node) bool {
	if slices.Contains([]string{"ReturnStatement", "ThrowStatement"}, n.Kind()) {
		return true
	}

	return n.Kind() == "BreakStatement" && n.Closest(engine.Loop).Node() == loop.Node()
}
