package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Decorators is the expressions a def or class is decorated with, in source order.
func (n Node) Decorators() []Node {
	return n.ChildrenIn("decorator_list")
}

// IsDecoratedWith says whether a decorator, or the call a decorator makes, is spelled as one of the dotted names.
func (n Node) IsDecoratedWith(dotted ...string) bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		if decorator.Kind() == "Call" {
			decorator = decorator.Child("func")
		}

		return slices.Contains(dotted, decorator.DottedName())
	})
}

// IsMethod says whether the def stands in a class body.
func (n Node) IsMethod() bool {
	return n.IsFunction() && n.Parent().Kind() == "ClassDef"
}

// IsStatic says whether the def is a @staticmethod.
func (n Node) IsStatic() bool {
	return n.decoratedPlainly("staticmethod")
}

// IsClassMethod says whether the def is a @classmethod.
func (n Node) IsClassMethod() bool {
	return n.decoratedPlainly("classmethod")
}

// IsPropertyGetter says whether the def is a @property.
func (n Node) IsPropertyGetter() bool {
	return n.decoratedPlainly("property")
}

// decoratedPlainly says whether a decorator is spelled as the dotted name itself, not called.
func (n Node) decoratedPlainly(dotted string) bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool { return decorator.DottedName() == dotted })
}

// IsDunder says whether the def is a __special__ method.
func (n Node) IsDunder() bool {
	return strings.HasPrefix(n.Name(), "__") && strings.HasSuffix(n.Name(), "__")
}

// TakesKeywordRest says whether the def takes **kwargs.
func (n Node) TakesKeywordRest() bool {
	return n.Child("args").Child("kwarg").Exists()
}

// ReturnsNothing says whether the def's written return type is None, NoReturn or Never.
func (n Node) ReturnsNothing() bool {
	returns := n.Child("returns")
	if returns.IsNone() {
		return true
	}

	return slices.Contains([]string{"NoReturn", "typing.NoReturn", "Never", "typing.Never"}, returns.DottedName())
}

// ReturnedValues is the value of every return in the def's body that returns one, nested defs included.
func (n Node) ReturnedValues() []Node {
	var values []Node
	for _, statement := range n.statementsIn() {
		if value := statement.Child("value"); statement.Kind() == "Return" && value.Exists() {
			values = append(values, value)
		}
	}

	return values
}

// IsBailOut says whether the statement leaves where it stands: a return, a raise, a break, a continue.
func (n Node) IsBailOut() bool {
	return n.Is(engine.BailOut)
}

// IsDataclass says whether the class is decorated as a dataclass, called or not.
func (n Node) IsDataclass() bool {
	return n.IsDecoratedWith("dataclass", "dataclasses.dataclass")
}

// ComparesByIdentity says whether the dataclass gives up the value equality a dataclass is given, `eq=False` on its
// decorator, so it is an entity known by its identity rather than a value.
func (n Node) ComparesByIdentity() bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		return decorator.Kind() == "Call" && slices.Contains([]string{"dataclass", "dataclasses.dataclass"}, decorator.Child("func").DottedName()) && decorator.Keyword("eq").IsFalse()
	})
}

// Initializer is the class's __init__; no node when it declares none.
func (n Node) Initializer() Node {
	for _, member := range n.ChildrenIn("body") {
		if member.IsFunction() && member.Name() == "__init__" {
			return member
		}
	}

	return Node{}
}

// Methods is every def the class body declares, in source order.
func (n Node) Methods() []Node {
	var methods []Node
	for _, member := range n.ChildrenIn("body") {
		if member.IsFunction() {
			methods = append(methods, member)
		}
	}

	return methods
}
