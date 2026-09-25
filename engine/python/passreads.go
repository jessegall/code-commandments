package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// ArgumentSubjectType is the one class every argument of the call asks things of: each argument reads an
// attribute off a receiver mypy types as that class. None when an argument asks nothing, or they ask of several.
func (n Node) ArgumentSubjectType() (string, bool) {
	var types []string
	for _, argument := range append(n.Arguments(), n.Keywords()...) {
		asks := slices.DeleteFunc(argument.argumentValue().evaluatedIn(), func(part Node) bool { return part.Kind() != "Attribute" })
		if len(asks) == 0 {
			return "", false
		}
		for _, ask := range asks {
			class, ok := ask.Child("value").ResolvedClass()
			if !ok {
				return "", false
			}
			if !slices.Contains(types, class) {
				types = append(types, class)
			}
		}
	}
	if len(types) != 1 {
		return "", false
	}

	return types[0], true
}

// ResolvedClass is the class mypy types the expression as, None aside.
func (n Node) ResolvedClass() (string, bool) {
	resolved := n.Node().Resolved
	if resolved == nil || resolved.Kind != "named" {
		return "", false
	}

	return resolved.Name, true
}

// ConversionIn is the conversion an argument is built by: the class a call of one argument constructs, or the
// class and method it is converted through, `Money.of`.
func (n Node) ConversionIn(argument Node) (string, bool) {
	arguments := argument.Arguments()
	if !argument.IsCall() || len(arguments) != 1 || len(argument.Keywords()) > 0 || isStarred(arguments[0]) {
		return "", false
	}
	callee := argument.Callee()
	named := callee
	if callee.Kind() == "Attribute" {
		named = callee.Child("value")
	}
	resolved := named.Node().Resolved
	if resolved == nil || resolved.Constructs == "" {
		return "", false
	}
	if callee.Kind() == "Attribute" {
		return resolved.Constructs + "." + callee.Name(), true
	}

	return resolved.Constructs, true
}

// DecidesOnBoolsAlone says whether every parameter of the def after the receiver is a `bool` it tests, negates or
// joins with `and`/`or`: a signature that names none of the subject those flags describe. A constructor's
// parameters are the object's own fields being born.
func (n Node) DecidesOnBoolsAlone() bool {
	if !n.IsFunction() || n.Name() == "__init__" {
		return false
	}
	parameters := n.Parameters()
	if n.IsMethod() && !n.IsStatic() && len(parameters) > 0 {
		parameters = parameters[1:]
	}

	return len(parameters) > 0 && !slices.ContainsFunc(parameters, func(parameter Node) bool {
		return parameter.Child("annotation").DottedName() != "bool" || !n.readsAsCondition(parameter.Name())
	})
}

// readsAsCondition says whether the def reads the name as a condition: tested bare, matched on, negated or joined.
func (n Node) readsAsCondition(name string) bool {
	return slices.ContainsFunc(n.ExpressionsIn(), func(read Node) bool {
		around := read.wrapper()

		return read.Kind() == "Name" && read.Name() == name && (read.isTested() || read.isMatchedOn() || around.IsNegation() || around.IsShortCircuit())
	})
}

// isMatchedOn says whether the expression is what a match statement matches, alone or in a tuple.
func (n Node) isMatchedOn() bool {
	subject := n
	if around := n.wrapper(); around.Kind() == "Tuple" {
		subject = around
	}

	return subject.Parent().Kind() == "Match" && subject.Node().Field == "subject"
}

// SwitchesEntirelyOnAParameter says whether the def's whole body is a two-way choice made on one of its parameters:
// a flag it tests, or an optional it asks is None. Two defs share one name.
func (n Node) SwitchesEntirelyOnAParameter() bool {
	return slices.ContainsFunc(n.Parameters(), func(parameter Node) bool {
		return twoWayBranch(n.StatementsBeyondText(), parameter.isSelectedBy)
	})
}

// twoWayBranch says whether the statements are one choice between two pieces of work the test decides: an if and
// else, an if that returns before the rest, or a return of a conditional expression.
func twoWayBranch(statements []Node, tests func(Node) bool) bool {
	if len(statements) == 1 {
		only := statements[0]
		if only.Kind() == "Return" {
			value := only.ReturnedValue()

			return value.Kind() == "IfExp" && !value.Child("body").isBareValue() && !value.Child("orelse").isBareValue() && tests(value.Child("test"))
		}
		orelse := only.ChildrenIn("orelse")

		return only.Kind() == "If" && len(orelse) > 0 && !isElif(nodeMatches(orelse)) && doesWork(only.ChildrenIn("body")) && doesWork(orelse) && tests(only.Child("test"))
	}
	if len(statements) != 2 || statements[0].Kind() != "If" {
		return false
	}
	first, body := statements[0], statements[0].ChildrenIn("body")

	return len(first.ChildrenIn("orelse")) == 0 && len(body) > 0 && body[len(body)-1].Kind() == "Return" && doesWork(body) && doesWork(statements[1:]) && tests(first.Child("test"))
}

// doesWork says whether the statements do something: more than one, or one that is no no-op and returns no bare
// value.
func doesWork(statements []Node) bool {
	if len(statements) != 1 {
		return len(statements) > 0
	}

	return !statements[0].isNoOp() && !statements[0].ReturnedValue().isBareValue()
}

// isNoOp says whether the statement does nothing: a continue, a placeholder, or a return of an absent value.
func (n Node) isNoOp() bool {
	return n.Kind() == "Continue" || n.IsPlaceholder() || n.returnsAbsence()
}

// isBareValue says whether the expression is a literal or a name: a value, not work. An f-string builds its text,
// so it is work.
func (n Node) isBareValue() bool {
	return n.Kind() == "Constant" || n.Kind() == "Name"
}

// isSelectedBy says whether the test picks between two behaviours on the parameter: a bool flag tested bare or
// negated, or an optional asked whether it is None.
func (n Node) isSelectedBy(test Node) bool {
	tested := test
	if test.IsNegation() {
		tested = test.Child("operand")
	}
	flag := n.Child("annotation").DottedName() == "bool" && tested.Kind() == "Name" && tested.Name() == n.Name()
	_, optional := n.Child("annotation").OptionalOf()

	return flag || (optional && n.Default().IsNone() && test.testsNoneOf(n.Name()))
}

// nodeMatches is the nodes' matches, for the reads that take them.
func nodeMatches(nodes []Node) []engine.Match {
	matches := make([]engine.Match, len(nodes))
	for at, node := range nodes {
		matches[at] = node.Match
	}

	return matches
}

// ReceiverName is the name a method's receiver goes by where the node sits; empty outside an instance method.
func (n Node) ReceiverName() string {
	function := n.EnclosingFunction()
	parameters := function.Parameters()
	if !function.IsMethod() || function.IsStatic() || len(parameters) == 0 {
		return ""
	}

	return parameters[0].Name()
}

// TakesAnything says whether a parameter is annotated to take any value at all.
func (n Node) TakesAnything() bool {
	return slices.Contains([]string{"Any", "typing.Any", "object"}, n.Child("annotation").DottedName())
}

// IsScalarParameter says whether a parameter is annotated as a builtin scalar.
func (n Node) IsScalarParameter() bool {
	return slices.Contains(scalars, n.Child("annotation").DottedName())
}

// ModuleOfClass is the module a fully qualified class name lives in, when the program holds it.
func (p *Program) ModuleOfClass(class string) (*Module, bool) {
	at := strings.LastIndex(class, ".")
	if at < 0 {
		return nil, false
	}

	return p.ModuleCalled(class[:at])
}
