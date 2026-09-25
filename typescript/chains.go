package typescript

import "slices"

// unwrap is the expression a parenthesised or non-null-asserted one holds: (a.b) and a.b! read as a.b.
func (n Node) unwrap() Node {
	for n.Kind() == "ParenthesizedExpression" || n.Kind() == "NonNullExpression" {
		n = Node{n.Child("expression")}
	}

	return n
}

// Chain is a pure member chain as the names it reads, root first: ["order", "customer", "name"] for
// order.customer.name, ["order"] for a bare name. False for anything else: a call, an index, `this`.
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

// Chains is every member chain of two or more names the expression reads, a call's receiver and arguments
// included, a chain's own prefixes not repeated.
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

// MemberDepth is how many property hops past its root the deepest data reach in the expression takes:
// 2 for order.customer.name. Accessors named in transparent, such as value or length, add no hop; an
// assignment, a block-bodied function and a template string reach nothing.
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
		if n.Is("assignment") {
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

// Roots is every name the expression reads its data from: form for form.email, both a and i for a[i];
// a name an arrow function binds is its own, not a root.
func (n Node) Roots() []string {
	n = n.unwrap()
	var roots []string
	switch n.Kind() {
	case "Identifier":
		if n.Is("identifier") {
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
	slices.Sort(roots)

	return slices.Compact(roots)
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
