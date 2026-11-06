package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) Decorators() []Node {
	return n.ChildrenIn("decorator_list")
}

func (n Node) IsDecoratedWith(dotted ...string) bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		if decorator.Kind() == "Call" {
			decorator = decorator.Child("func")
		}

		return slices.Contains(dotted, decorator.DottedName())
	})
}

func (n Node) IsMethod() bool {
	return n.IsFunction() && n.Parent().Kind() == "ClassDef"
}

func (n Node) IsStatic() bool {
	return n.decoratedPlainly("staticmethod")
}

func (n Node) IsClassMethod() bool {
	return n.decoratedPlainly("classmethod")
}

func (n Node) IsPropertyGetter() bool {
	return n.decoratedPlainly("property")
}

func (n Node) decoratedPlainly(dotted string) bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool { return decorator.DottedName() == dotted })
}

func (n Node) IsDunder() bool {
	return strings.HasPrefix(n.Name(), "__") && strings.HasSuffix(n.Name(), "__")
}

func (n Node) TakesKeywordRest() bool {
	return n.Child("args").Child("kwarg").Exists()
}

func (n Node) ReturnsNothing() bool {
	returns := n.Child("returns")
	if returns.IsNone() {
		return true
	}

	return slices.Contains([]string{"NoReturn", "typing.NoReturn", "Never", "typing.Never"}, returns.DottedName())
}

func (n Node) ReturnedValues() []Node {
	var values []Node
	for _, statement := range n.statementsIn() {
		if value := statement.Child("value"); statement.Kind() == "Return" && value.Exists() {
			values = append(values, value)
		}
	}

	return values
}

func (n Node) IsBailOut() bool {
	return n.Is(engine.BailOut)
}

func (n Node) IsDataclass() bool {
	return n.IsDecoratedWith("dataclass", "dataclasses.dataclass")
}

func (n Node) ComparesByIdentity() bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		return decorator.Kind() == "Call" && slices.Contains([]string{"dataclass", "dataclasses.dataclass"}, decorator.Child("func").DottedName()) && decorator.Keyword("eq").IsFalse()
	})
}

func (n Node) Initializer() Node {
	for _, member := range n.ChildrenIn("body") {
		if member.IsFunction() && member.Name() == "__init__" {
			return member
		}
	}

	return Node{}
}

func (n Node) Methods() []Node {
	var methods []Node
	for _, member := range n.ChildrenIn("body") {
		if member.IsFunction() {
			methods = append(methods, member)
		}
	}

	return methods
}
