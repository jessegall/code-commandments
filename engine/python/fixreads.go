package python

import "slices"

var (
	// genericExceptions is the exceptions that name no failure: raising one with a message puts the name in prose.
	genericExceptions = []string{"Exception", "BaseException", "RuntimeError"}
	// rootExceptions is the exceptions a handler catching everything names.
	rootExceptions = []string{"Exception", "BaseException"}
)

// IsGenericWithMessage says whether the raise builds a generic exception from a message string: the failure named
// in prose rather than by a type.
func (n Node) IsGenericWithMessage() bool {
	raised := n.Child("exc")
	arguments := append(raised.Arguments(), raised.Keywords()...)
	if n.Kind() != "Raise" || !raised.IsCall() || !slices.Contains(genericExceptions, raised.Callee().DottedName()) || len(arguments) == 0 {
		return false
	}
	slices.SortFunc(arguments, func(a, b Node) int { return a.Node().Span.Start - b.Node().Span.Start })
	message := arguments[0]

	return message.Kind() == "JoinedStr" || message.Node().Literal == "string"
}

// IsRaiseWithoutCause says whether the statement raises a new exception inside a handler without chaining the one
// it caught: `raise X` where `raise X from error` keeps the cause.
func (n Node) IsRaiseWithoutCause() bool {
	raised := n.Child("exc")
	if n.Kind() != "Raise" || !raised.Exists() || n.Child("cause").Exists() {
		return false
	}
	handler := n.enclosingHandler()
	dotted := raised.DottedName()

	return handler.Exists() && (handler.Name() == "" || dotted != handler.Name()) && !handler.setsCauseOf(dotted)
}

// enclosingHandler is the except clause the statement sits in, within its own def; no node outside one.
func (n Node) enclosingHandler() Node {
	for around := n.Parent(); around.Exists() && !around.IsDefinition(); around = around.Parent() {
		if around.Kind() == "ExceptHandler" {
			return around
		}
	}

	return Node{}
}

// setsCauseOf says whether the handler assigns the raised exception's cause by hand: `error.__cause__ = caught`.
func (n Node) setsCauseOf(raised string) bool {
	return raised != "" && slices.ContainsFunc(n.Descendants(), func(assign Node) bool {
		return assign.Kind() == "Assign" && slices.ContainsFunc(assign.ChildrenIn("targets"), func(target Node) bool { return target.DottedName() == raised+".__cause__" })
	})
}

// IsBroad says whether the except clause catches everything: bare, or naming a root exception.
func (n Node) IsBroad() bool {
	caught := n.Child("type")
	if !caught.Exists() {
		return true
	}
	types := []Node{caught}
	if caught.Kind() == "Tuple" {
		types = caught.ChildrenIn("elts")
	}

	return slices.ContainsFunc(types, func(kind Node) bool { return slices.Contains(rootExceptions, kind.DottedName()) })
}

// Swallows says whether the except clause's body is one statement that does nothing with the failure.
func (n Node) Swallows() bool {
	body := n.ChildrenIn("body")

	return len(body) == 1 && body[0].isNoOp()
}

// ConstructorHasSideEffect says whether the class's __init__ calls a method on a collaborator it was handed, or
// one it holds from them, and throws the result away, outside any try that probes it: building the object changes
// something outside it.
func (n Node) ConstructorHasSideEffect() bool {
	init := n.Initializer()

	return n.Kind() == "ClassDef" && init.Exists() && slices.ContainsFunc(init.ExpressionsIn(), func(expression Node) bool {
		return expression.ResultIsDiscarded() && !expression.isProbed() && expression.actsOnCollaborator(init)
	})
}

// owner is the statement or definition that holds the expression.
func (n Node) owner() Node {
	around := n.Parent()
	for around.Exists() && !around.IsStatement() {
		around = around.Parent()
	}

	return around
}

// isProbed says whether the expression's statement sits, at any depth, in the body of a try that handles what
// it raises.
func (n Node) isProbed() bool {
	for statement := n.owner(); statement.Exists() && !statement.IsDefinition(); statement = statement.Parent() {
		try := statement.Parent()
		if (try.Kind() == "Try" || try.Kind() == "TryStar") && len(try.ChildrenIn("handlers")) > 0 && statement.Node().Field == "body" {
			return true
		}
	}

	return false
}

// actsOnCollaborator says whether the call is a method called on a collaborator __init__ was handed, or on one it
// holds from them.
func (n Node) actsOnCollaborator(init Node) bool {
	callee := n.Callee()
	if !n.IsCall() || callee.Kind() != "Attribute" {
		return false
	}
	receiver := callee.Child("value")
	var handed []string
	for at, parameter := range init.Parameters() {
		if field := parameter.Node().Field; at > 0 && field != "vararg" && field != "kwarg" {
			handed = append(handed, parameter.Name())
		}
	}
	held := receiver.ReachedThrough()

	return slices.Contains(handed, receiver.RootName()) || (held != "" && slices.Contains(init.heldCollaborators(handed), held))
}

// ReachedThrough is the attribute an expression reaches its object through: `self.client` for
// `self.client.get()`; empty for anything not reached through a name's attribute.
func (n Node) ReachedThrough() string {
	switch n.Kind() {
	case "Attribute":
		if n.Child("value").Kind() == "Name" {
			return n.DottedName()
		}

		return n.Child("value").ReachedThrough()
	case "Subscript":
		return n.Child("value").ReachedThrough()
	case "Call":
		return n.Callee().ReachedThrough()
	}

	return ""
}

// heldCollaborators is what __init__ stores from nothing but what it was handed.
func (n Node) heldCollaborators(handed []string) []string {
	sources := map[string][]string{}
	var order []string
	for _, assign := range n.statementsIn() {
		if assign.Kind() != "Assign" {
			continue
		}
		for _, target := range assign.ChildrenIn("targets") {
			if _, seen := sources[target.DottedName()]; !seen {
				order = append(order, target.DottedName())
			}
			sources[target.DottedName()] = append(sources[target.DottedName()], assign.Child("value").RootName())
		}
	}

	return slices.DeleteFunc(order, func(target string) bool {
		return slices.ContainsFunc(sources[target], func(root string) bool { return !slices.Contains(handed, root) })
	})
}

// IsStaticStateWrite says whether the statement, in a def, writes state that outlives every call: a global it
// declares, or an attribute of its class.
func (n Node) IsStaticStateWrite() bool {
	function := n.EnclosingFunction()

	return function.Exists() && slices.ContainsFunc(n.writtenTargets(), func(target Node) bool { return n.writesStatic(target, function) })
}

// writesStatic says whether the target is static state of the def: a name it declares global, a memo filled on
// first use aside, or an attribute of `type(self)`, its class, or a classmethod's `cls`.
func (n Node) writesStatic(target, function Node) bool {
	switch target.Kind() {
	case "Name":
		return slices.Contains(function.globalNames(), target.Name()) && !n.isMemoFill(target.Name())
	case "Attribute":
		owner := target.Child("value")

		return (owner.IsCall() && owner.Callee().DottedName() == "type") || (owner.Kind() == "Name" && slices.Contains(function.classNames(), owner.Name()))
	}

	return false
}

// globalNames is every name the def declares global in its own body.
func (n Node) globalNames() []string {
	var names []string
	for _, statement := range n.statementsIn() {
		if extras := statement.Node().Extras; statement.Kind() == "Global" && statement.EnclosingFunction() == n && extras != nil && extras.Python != nil {
			names = append(names, extras.Python.Names...)
		}
	}

	return names
}

// classNames is how a method names its own class: the class's name, and a classmethod's `cls`.
func (n Node) classNames() []string {
	if !n.IsMethod() {
		return nil
	}
	class := n.EnclosingClass()
	parameters := n.Parameters()
	if n.IsDecoratedWith("classmethod") && len(parameters) > 0 {
		return []string{class.Name(), parameters[0].Name()}
	}

	return []string{class.Name()}
}

// MissesToNone says whether the call is a mapping's `get` that answers a missing key with None.
func (n Node) MissesToNone() bool {
	callee, arguments := n.Callee(), n.Arguments()
	count := len(arguments) + len(n.Keywords())

	return n.IsCall() && callee.Kind() == "Attribute" && callee.Name() == "get" && (count == 1 || (count == 2 && len(arguments) == 2 && arguments[1].IsNone()))
}

// LooksUpOwnDict says whether the call reads one of its class's own attributes the class annotates as a mapping.
func (n Node) LooksUpOwnDict() bool {
	store := n.Callee().Child("value").SelfAttribute()
	class := n.EnclosingFunction().Parent()
	if store == "" || class.Kind() != "ClassDef" {
		return false
	}
	annotation, ok := class.AttributeAnnotation(store)
	named := annotation
	if annotation.Kind() == "Subscript" {
		named = annotation.Child("value")
	}

	return ok && slices.Contains(dictTypes, named.DottedName())
}
