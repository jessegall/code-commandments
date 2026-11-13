package python

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

var constantName = regexp.MustCompile(`^_*[A-Z][A-Z0-9_]*$`)

func (p *Program) membersAbove(n Node) ([]Node, bool) {
	class := n.Parent()
	if class.Kind() != "ClassDef" || n.Node().Field != "body" || p.IsEnumClass(class) {
		return nil, false
	}
	body := class.ChildrenIn("body")

	return body[:slices.Index(body, n)], true
}

func (p *Program) IsMemberAfterMethod(n Node) bool {
	targets := n.writtenTargets()
	if len(targets) == 0 || !slices.ContainsFunc(targets, func(target Node) bool { return !isDunderName(target.DottedName()) }) {
		return false
	}
	above, ok := p.membersAbove(n)
	if !ok {
		return false
	}
	read := n.namesRead()
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

func (n Node) namesRead() []string {
	var read []string
	for _, expression := range n.ownExpressions() {
		read = append(read, expression.DataNames()...)
	}

	return read
}

func (p *Program) IsConstantBelowField(n Node) bool {
	above, ok := p.membersAbove(n)
	if !ok || !p.declaresConstant(n) || slices.ContainsFunc(above, Node.IsFunction) {
		return false
	}
	var fields []string
	for _, member := range above {
		if member.isStateDeclaration() && !p.declaresConstant(member) {
			for _, target := range member.writtenTargets() {
				fields = append(fields, target.Name())
			}
		}
	}

	return len(fields) > 0 && !slices.ContainsFunc(n.namesRead(), func(name string) bool { return slices.Contains(fields, name) })
}

func (p *Program) declaresConstant(n Node) bool {
	return n.DeclaresConstant() || slices.ContainsFunc(n.writtenTargets(), func(target Node) bool {
		_, ok := p.ConstantOf(n.Parent(), target.Name())

		return target.Kind() == "Name" && ok
	})
}

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

func (p *Program) IsBareStatePredicate(n Node) bool {
	return n.IsMethod() && len(n.Parameters()) == 1 && n.Child("returns").DottedName() == "bool" &&
		!prose.ReadsAsQuestion(n.Name()) && prose.IsThirdPerson(n.Name()) && p.ownsItsName(n)
}

func (p *Program) IsNarratedCommand(n Node) bool {
	if !n.IsMethod() || !prose.IsThirdPerson(n.Name()) {
		return false
	}

	return (n.ReturnsNothing() || n.isFluent()) && p.ownsItsName(n)
}

func (p *Program) ownsItsName(n Node) bool {
	return !n.IsDunder() && !p.IsOverride(n) && !p.IsOverridden(n) && !p.ExtendsOutside(n)
}

func (n Node) isFluent() bool {
	class := n.Parent()
	returns := n.Child("returns").SpelledType()

	return class.Kind() == "ClassDef" && !n.IsStatic() && !n.IsClassMethod() &&
		slices.Contains([]string{"Self", "typing.Self", "typing_extensions.Self", class.Name()}, returns) && !prose.IsRelationalCompound(n.Name())
}

func (n Node) soleStatement() (Node, bool) {
	body := n.StatementsBeyondText()
	if len(body) != 1 {
		return Node{}, false
	}

	return body[0], true
}

func (n Node) IsSoleReturnExpression() bool {
	only, ok := n.soleStatement()

	return ok && only.ReturnedValue().Exists()
}

func (n Node) IsSoleExpressionStatement() bool {
	only, ok := n.soleStatement()

	return ok && only.Kind() == "Expr"
}

func (n Node) IsDeclarationTable() bool {
	body := n.StatementsBeyondText()
	if len(body) < 2 {
		return false
	}

	row := body[0].Child("value").Callee()
	for _, statement := range body {
		call := statement.Child("value")
		if statement.Kind() != "Expr" || !call.IsCall() || !call.Callee().SameSyntax(row.Match) || !call.handsOnlyDeclaredValues() {
			return false
		}
	}

	return true
}

func (n Node) handsOnlyDeclaredValues() bool {
	return !slices.ContainsFunc(append(n.Arguments(), n.Keywords()...), func(argument Node) bool { return !argument.argumentValue().isDeclaredValue() })
}

func (n Node) isDeclaredValue() bool {
	switch n.Kind() {
	case "Constant", "Name", "Attribute":
		return true
	case "List", "Tuple", "Set":
		return !slices.ContainsFunc(n.ChildrenIn("elts"), func(element Node) bool { return !element.isDeclaredValue() })
	}

	return false
}

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
