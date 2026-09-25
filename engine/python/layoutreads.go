package python

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

// constantName is how a constant's name is spelled: capitals, digits and underscores.
var constantName = regexp.MustCompile(`^_*[A-Z][A-Z0-9_]*$`)

// membersAbove is the statements of the class body written before this one; false outside a class body, or in an
// enum's, whose members are its values.
func (p *Program) membersAbove(n Node) ([]Node, bool) {
	class := n.Parent()
	if class.Kind() != "ClassDef" || n.Node().Field != "body" || p.IsEnum(class.Name()) {
		return nil, false
	}
	body := class.ChildrenIn("body")

	return body[:slices.Index(body, n)], true
}

// IsMemberAfterMethod says whether the statement declares state in a class body below a method, and reads none of
// the methods above it: a field or constant out of the class's order.
func (p *Program) IsMemberAfterMethod(n Node) bool {
	targets := n.writtenTargets()
	if len(targets) == 0 || !slices.ContainsFunc(targets, func(target Node) bool { return !isDunderName(target.DottedName()) }) {
		return false
	}
	above, ok := p.membersAbove(n)
	if !ok {
		return false
	}
	var read []string
	for _, expression := range n.ownExpressions() {
		read = append(read, expression.DataNames()...)
	}
	var methods []string
	for _, member := range above {
		if member.IsFunction() {
			methods = append(methods, member.Name())
		}
	}

	return len(methods) > 0 && !slices.ContainsFunc(read, func(name string) bool { return slices.Contains(methods, name) })
}

func isDunderName(name string) bool {
	return strings.HasPrefix(name, "__") && strings.HasSuffix(name, "__")
}

// IsConstantBelowField says whether the statement declares a constant below a field, before any method: the
// class's constants belong above its fields.
func (p *Program) IsConstantBelowField(n Node) bool {
	if !n.DeclaresConstant() {
		return false
	}
	above, ok := p.membersAbove(n)

	return ok && !slices.ContainsFunc(above, Node.IsFunction) && slices.ContainsFunc(above, func(member Node) bool {
		return member.isStateDeclaration() && !member.DeclaresConstant()
	})
}

// DeclaresConstant says whether the statement declares a constant: a name spelled in capitals, or one annotated
// Final or ClassVar.
func (n Node) DeclaresConstant() bool {
	switch n.Kind() {
	case "Assign":
		return !slices.ContainsFunc(n.ChildrenIn("targets"), func(target Node) bool { return target.Kind() != "Name" || !constantName.MatchString(target.Name()) })
	case "AnnAssign":
		annotation := n.Child("annotation")
		if annotation.Kind() == "Subscript" {
			annotation = annotation.Child("value")
		}
		target := n.Child("target")

		return slices.Contains([]string{"Final", "typing.Final", "ClassVar", "typing.ClassVar"}, annotation.DottedName()) || (target.Kind() == "Name" && constantName.MatchString(target.Name()))
	}

	return false
}

func (n Node) isStateDeclaration() bool {
	return n.Kind() == "Assign" || n.Kind() == "AnnAssign"
}

// IsBareStatePredicate says whether the method answers bool about its own state with a name that narrates rather
// than asks: `hides()` where `is_hidden()` is the question.
func (p *Program) IsBareStatePredicate(n Node) bool {
	return n.IsMethod() && len(n.Parameters()) == 1 && n.Child("returns").DottedName() == "bool" &&
		!prose.ReadsAsQuestion(n.Name()) && prose.IsThirdPerson(n.Name()) && p.ownsItsName(n)
}

// IsNarratedCommand says whether the method is a command, returning nothing or itself, named in the third person:
// `hides()` where `hide()` is the order.
func (p *Program) IsNarratedCommand(n Node) bool {
	if !n.IsMethod() || !prose.IsThirdPerson(n.Name()) {
		return false
	}

	return (n.ReturnsNothing() || n.isFluent()) && p.ownsItsName(n)
}

// ownsItsName says whether the method chose its own name: no dunder, and no contract with a base or a subclass
// that names it.
func (p *Program) ownsItsName(n Node) bool {
	return !n.IsDunder() && !p.IsOverride(n) && !p.IsOverridden(n) && !p.ExtendsOutside(n)
}

// isFluent says whether the method returns its own class, as a fluent declaration does, relating its receiver
// to nothing.
func (n Node) isFluent() bool {
	class := n.Parent()
	returns := n.Child("returns").SpelledType()

	return class.Kind() == "ClassDef" && !n.IsStatic() && !n.IsClassMethod() &&
		slices.Contains([]string{"Self", "typing.Self", "typing_extensions.Self", class.Name()}, returns) && !prose.IsRelationalCompound(n.Name())
}

// soleStatement is the one statement the def's body runs, a docstring aside; false for a body of any other length.
func (n Node) soleStatement() (Node, bool) {
	body := n.StatementsBeyondText()
	if len(body) != 1 {
		return Node{}, false
	}

	return body[0], true
}

// IsSoleReturnExpression says whether the def's body is exactly `return <value>`.
func (n Node) IsSoleReturnExpression() bool {
	only, ok := n.soleStatement()

	return ok && only.ReturnedValue().Exists()
}

// IsSoleExpressionStatement says whether the def's body is exactly one expression standing as a statement.
func (n Node) IsSoleExpressionStatement() bool {
	only, ok := n.soleStatement()

	return ok && only.Kind() == "Expr"
}

// IsLiteralLookup says whether the def is a lookup table written as code: it calls nothing, and every value it
// returns is a literal. Two that differ are two tables of data, not one procedure written twice.
func (n Node) IsLiteralLookup() bool {
	var returns []Node
	for _, statement := range n.ChildrenIn("body") {
		for _, node := range append([]Node{statement}, statement.Descendants()...) {
			if node.IsCall() {
				return false
			}
			if node.Kind() == "Return" {
				returns = append(returns, node)
			}
		}
	}

	return len(returns) > 0 && !slices.ContainsFunc(returns, func(ret Node) bool { return ret.ReturnedValue().Kind() != "Constant" })
}
