package csharp

import (
	"github.com/jessegall/code-commandments/engine"
)

func (n Node) BodyHash() string {
	body := n.FunctionBody()
	if !body.Exists() {
		return ""
	}

	return engine.SyntaxHash([]engine.Match{body.Match}, hashing, false)
}

func (n Node) ShapeHash() string {
	body := n.FunctionBody()
	if !body.Exists() {
		return ""
	}

	return engine.SyntaxHash([]engine.Match{body.Match}, hashing, true)
}

func (n Node) BodyWeight() int {
	body := n.FunctionBody()
	if !body.Exists() {
		return 0
	}

	return hashing.Weight(body.Match) + engine.SyntaxWeight(body.Match.Children(), hashing)
}

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

func (n Node) IsSoleReturnExpression() bool {
	return n.soleStatement().ReturnedValue().Exists()
}

func (n Node) IsSoleExpressionStatement() bool {
	return n.soleStatement().IsExpressionStatement()
}

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
