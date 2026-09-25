package csharp

import (
	"github.com/jessegall/code-commandments/engine"
)

// BodyHash is a formatting-blind fingerprint of the body the function runs, its name left out: two names for one
// body are the same code. Empty for a node that runs none.
func (n Node) BodyHash() string {
	body := n.FunctionBody()
	if !body.Exists() {
		return ""
	}

	return engine.SyntaxHash([]engine.Match{body.Match}, hashing, false)
}

// ShapeHash is the body's fingerprint blind to local names and data literals too: one skeleton, whatever its locals
// are called and whichever constants it uses.
func (n Node) ShapeHash() string {
	body := n.FunctionBody()
	if !body.Exists() {
		return ""
	}

	return engine.SyntaxHash([]engine.Match{body.Match}, hashing, true)
}

// BodyWeight is how many nodes make up the body the function runs, the body itself counted: the size a clone rule
// floors trivial bodies by. Zero for a node that runs none.
func (n Node) BodyWeight() int {
	body := n.FunctionBody()
	if !body.Exists() {
		return 0
	}

	return hashing.Weight(body.Match) + engine.SyntaxWeight(body.Match.Children(), hashing)
}

// soleStatement is the one statement the body runs: an expression body, or a block of one; no node otherwise.
func (n Node) soleStatement() Node {
	body := n.FunctionBody()
	if body.IsReturn() || body.IsExpressionStatement() {
		return body
	}
	var counted []Node
	for _, child := range body.Children() {
		if !child.Is("AttributeList") {
			counted = append(counted, child)
		}
	}
	if len(counted) != 1 {
		return Node{}
	}
	if inner, unwrapped := (hashRules{}).Unwrap(counted[0].Match); unwrapped {
		return Node{inner}
	}

	return counted[0]
}

// IsSoleReturnExpression says whether the function only hands back one value.
func (n Node) IsSoleReturnExpression() bool {
	return n.soleStatement().ReturnedValue().Exists()
}

// IsSoleExpressionStatement says whether the function only runs one expression for its effect.
func (n Node) IsSoleExpressionStatement() bool {
	return n.soleStatement().IsExpressionStatement()
}

// IsLiteralLookup says whether the function is a table: it calls nothing, and every value it returns is a constant.
func (n Node) IsLiteralLookup() bool {
	body := n.FunctionBody()
	if !body.Exists() {
		return false
	}
	returns := 0
	for _, node := range append([]Node{body}, body.Descendants()...) {
		for _, expression := range node.Expressions() {
			for _, part := range expression.Flatten() {
				if part.IsCall() {
					return false
				}
			}
		}
		if !node.IsReturn() {
			continue
		}
		returns++
		value := node.ReturnedValue()
		if !value.Exists() {
			return false
		}
		for _, answer := range value.Answers() {
			if !answer.IsConstant() {
				return false
			}
		}
	}

	return returns > 0
}
