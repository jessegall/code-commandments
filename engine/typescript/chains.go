package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var wrappers = []string{"ParenthesizedExpression", "NonNullExpression", "AsExpression", "SatisfiesExpression", "TypeAssertionExpression"}

func (n Node) unwrap() Node {
	for slices.Contains(wrappers, n.Kind()) {
		n = Node{n.Child("expression")}
	}

	return n
}

func (n Node) Chain() ([]string, bool) {
	n = n.unwrap()
	switch n.Kind() {
	case "Identifier":
		return []string{n.Name()}, true
	case "PropertyAccessExpression":
		base, ok := Node{n.Child("expression")}.Chain()
		if !ok {
			return nil, false
		}

		return append(base, n.Name()), true
	}

	return nil, false
}

func (n Node) Chains() [][]string {
	n = n.unwrap()
	if n.Kind() == "CallExpression" || n.Kind() == "NewExpression" {
		callee := Node{n.Child("expression")}.unwrap()
		receiver := callee
		if callee.Kind() == "PropertyAccessExpression" || callee.Kind() == "ElementAccessExpression" {
			receiver = Node{callee.Child("expression")}
		}
		chains := receiver.Chains()
		for _, argument := range n.ChildrenIn("arguments") {
			chains = append(chains, Node{argument}.Chains()...)
		}

		return chains
	}
	if chain, ok := n.Chain(); ok {
		if len(chain) >= 2 {
			return [][]string{chain}
		}

		return nil
	}
	var chains [][]string
	for _, child := range n.Children() {
		if child.Node().Role != "type" {
			chains = append(chains, Node{child}.Chains()...)
		}
	}

	return chains
}

func (n Node) MemberDepth(transparent ...string) int {
	n = n.unwrap()
	child := func(field string) int { return Node{n.Child(field)}.MemberDepth(transparent...) }
	switch n.Kind() {
	case "PropertyAccessExpression":
		return max(n.chainLength(transparent), child("expression"))
	case "ElementAccessExpression":
		return max(n.chainLength(transparent), child("expression"), child("argumentExpression"))
	case "CallExpression", "NewExpression":
		deepest := n.calleeDepth(transparent)
		for _, argument := range n.ChildrenIn("arguments") {
			deepest = max(deepest, Node{argument}.MemberDepth(transparent...))
		}

		return deepest
	case "PrefixUnaryExpression", "PostfixUnaryExpression":
		return child("operand")
	case "TypeOfExpression", "AwaitExpression", "VoidExpression", "DeleteExpression":
		return child("expression")
	case "BinaryExpression":
		if n.Is(engine.Assignment) {
			return 0
		}

		return max(child("left"), child("right"))
	case "ConditionalExpression":
		return max(child("condition"), child("whenTrue"), child("whenFalse"))
	case "ArrayLiteralExpression":
		return n.deepestOf("elements", transparent)
	case "ObjectLiteralExpression":
		deepest := 0
		for _, property := range n.ChildrenIn("properties") {
			value := property.Child("initializer")
			if property.Kind() == "SpreadAssignment" {
				value = property.Child("expression")
			}
			deepest = max(deepest, Node{value}.MemberDepth(transparent...))
		}

		return deepest
	case "ArrowFunction":
		if body := n.Child("body"); body.Kind() != "Block" {
			return Node{body}.MemberDepth(transparent...)
		}
	}

	return 0
}

func (n Node) Roots() []string {
	n = n.unwrap()
	var roots []string
	switch n.Kind() {
	case "Identifier":
		if n.Is(engine.Identifier) {
			roots = []string{n.Name()}
		}
	case "PropertyAccessExpression":
		roots = Node{n.Child("expression")}.Roots()
	case "ArrowFunction":
		var bound []string
		for _, parameter := range n.ChildrenIn("parameters") {
			bound = append(bound, parameter.Child("name").Name())
		}
		for _, root := range (Node{n.Child("body")}).Roots() {
			if !slices.Contains(bound, root) {
				roots = append(roots, root)
			}
		}
	default:
		for _, child := range n.Children() {
			if child.Node().Role != "type" {
				roots = append(roots, Node{child}.Roots()...)
			}
		}
	}
	var once []string
	for _, root := range roots {
		if !slices.Contains(once, root) {
			once = append(once, root)
		}
	}

	return once
}

func (n Node) chainLength(transparent []string) int {
	switch n.Kind() {
	case "PropertyAccessExpression":
		hop := 1
		if slices.Contains(transparent, n.Name()) {
			hop = 0
		}

		return Node{n.Child("expression")}.unwrap().chainLength(transparent) + hop
	case "ElementAccessExpression":
		return Node{n.Child("expression")}.unwrap().chainLength(transparent) + 1
	}

	return 0
}

func (n Node) calleeDepth(transparent []string) int {
	callee := Node{n.Child("expression")}.unwrap()
	if callee.Kind() == "PropertyAccessExpression" || callee.Kind() == "ElementAccessExpression" {
		return Node{callee.Child("expression")}.unwrap().chainLength(transparent)
	}

	return callee.MemberDepth(transparent...)
}

func (n Node) deepestOf(field string, transparent []string) int {
	deepest := 0
	for _, each := range n.ChildrenIn(field) {
		deepest = max(deepest, Node{each}.MemberDepth(transparent...))
	}

	return deepest
}
