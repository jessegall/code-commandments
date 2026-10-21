package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

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

// writesStatic says whether the target is static state of the def: a name it declares global, or an attribute of
// `type(self)`, its class, or a classmethod's `cls`. A memo filled on first use is neither, whichever of the two
// holds it: the write happens once and every later call reads what the first settled, which is the shape PHP's
// twin excuses on both.
func (n Node) writesStatic(target, function Node) bool {
	switch target.Kind() {
	case "Name":
		return slices.Contains(function.globalNames(), target.Name()) && !n.isMemoFill(target.Name())
	case "Attribute":
		owner := target.Child("value")
		if !(owner.IsCall() && owner.Callee().DottedName() == "type") && !(owner.Kind() == "Name" && slices.Contains(function.classNames(), owner.Name())) {
			return false
		}

		return !n.isMemoFill(target.DottedName())
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

// RethrowsUnchanged says whether the except clause's whole body is a raise of the very failure it caught, bare
// or naming the caught variable, with no cause of its own: the clause catches, adds nothing, and raises again,
// which is what writing no clause at all already does. A clause another clause follows is doing real work — it
// keeps the broader one below from catching what it names — so it is no part of this.
func (n Node) RethrowsUnchanged() bool {
	body := n.ChildrenIn("body")
	if n.Kind() != "ExceptHandler" || len(body) != 1 || body[0].Kind() != "Raise" {
		return false
	}
	if !n.isTheLastClause() {
		return false
	}

	return body[0].raisesWhatWasCaughtBy(n)
}

// isTheLastClause says whether no other except clause of the same try follows this one.
func (n Node) isTheLastClause() bool {
	clauses := n.Parent().ChildrenIn("handlers")

	return len(clauses) > 0 && clauses[len(clauses)-1].Node() == n.Node()
}

// raisesWhatWasCaughtBy says whether the raise hands on the failure the clause caught, untouched: a bare raise,
// or one naming the caught variable and nothing else. A `from` of its own changes what the failure carries.
func (n Node) raisesWhatWasCaughtBy(handler Node) bool {
	raised := n.Child("exc")
	if n.Child("cause").Exists() {
		return false
	}
	if !raised.Exists() {
		return true
	}

	return raised.Kind() == "Name" && raised.Name() == handler.Name()
}

// IsUnreadCollaborator says whether the node is an assignment in __init__ that keeps a collaborator the class
// was handed under a name of its own, which nothing anywhere then reads: the class asks for something it does
// not use, and the only way a reader learns that is to search for the name and find none. A class that could
// read an attribute without naming it answers no, since the read may well be somewhere this cannot see.
func (n Node) IsUnreadCollaborator(codebase *engine.Codebase) bool {
	held, kept := n.collaboratorKeptInInit()
	class := n.EnclosingClass()
	if !kept || !strings.HasPrefix(held, "_") {
		return false
	}
	if class.ReadsItsAttributesWithoutNamingThem() {
		return false
	}

	return !readsTheAttribute(codebase, held)
}

// collaboratorKeptInInit is the attribute name an __init__ assignment keeps a handed-in collaborator under, and
// whether the node is one: `self._mailer = mailer`, where the value is a parameter annotated as a class rather
// than text or a number, so what goes unread is a collaborator and not a setting.
func (n Node) collaboratorKeptInInit() (string, bool) {
	def := n.EnclosingFunction()
	targets := n.ChildrenIn("targets")
	if n.Kind() != "Assign" || len(targets) != 1 || !def.Exists() || def.Name() != "__init__" {
		return "", false
	}
	target, value := targets[0], n.Child("value")
	if target.Kind() != "Attribute" || target.RootName() != "self" || value.Kind() != "Name" {
		return "", false
	}
	for _, parameter := range def.Parameters() {
		if parameter.Name() == value.Name() && parameter.Child("annotation").namesAClass() {
			return target.Name(), true
		}
	}

	return "", false
}

// namesAClass says whether the annotation names a class rather than a builtin scalar or a container of them.
func (n Node) namesAClass() bool {
	named := n.DottedName()

	return named != "" && !slices.Contains([]string{"str", "int", "float", "bool", "bytes", "list", "dict", "set", "tuple", "frozenset", "Any", "typing.Any", "object"}, named)
}

// ReadsItsAttributesWithoutNamingThem says whether the class could read an attribute of its own without writing
// its name: it answers a read it does not declare, or hands its own __dict__ somewhere that reads every one.
func (n Node) ReadsItsAttributesWithoutNamingThem() bool {
	for _, method := range n.Methods() {
		if slices.Contains([]string{"__getattr__", "__getattribute__", "__setattr__", "__reduce__", "__getstate__"}, method.Name()) {
			return true
		}
	}

	return slices.ContainsFunc(n.Descendants(), readsEveryAttribute)
}

// readsEveryAttribute says whether the expression reads every attribute of the instance at once: its __dict__,
// or a call that unpacks the whole instance.
func readsEveryAttribute(n Node) bool {
	if n.Kind() == "Attribute" && n.Name() == "__dict__" {
		return true
	}

	return n.IsCall() && slices.Contains([]string{"vars", "dataclasses.asdict", "dataclasses.astuple"}, n.Callee().DottedName())
}

// readsTheAttribute says whether anything in the codebase names the attribute other than to assign it: a read
// of `<something>.<name>`, wherever it is, so a subclass or a caller reading it counts.
func readsTheAttribute(codebase *engine.Codebase, name string) bool {
	return In(codebase).
		WhereKind("Attribute").
		Where(func(node engine.Match) bool { return node.Name() == name }).
		Reject(func(node engine.Match) bool { return Node{Match: node}.isAssigned() }).
		Count() > 0
}

// isAssigned says whether the expression is what an assignment writes to.
func (n Node) isAssigned() bool {
	return n.Node().Field == "targets"
}

// FilledParameter is the parameter a def fills and never gives back, and whether it fills one: a parameter
// annotated as a class whose own attribute the body writes, in a def that returns None. The signature then says
// the object goes in and says nothing about it coming out changed, so the caller has to know.
func (n Node) FilledParameter() (string, bool) {
	if !n.IsFunction() || !n.ReturnsNothing() {
		return "", false
	}
	for _, parameter := range n.Parameters() {
		if parameter.Name() == "self" || !parameter.Child("annotation").namesAClass() {
			continue
		}
		if n.writesAnAttributeOf(parameter.Name()) {
			return parameter.Name(), true
		}
	}

	return "", false
}

// writesAnAttributeOf says whether the def writes an attribute of the name: `sheet.grams = …`.
func (n Node) writesAnAttributeOf(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(node Node) bool {
		targets := node.ChildrenIn("targets")

		return node.Kind() == "Assign" && len(targets) == 1 && targets[0].Kind() == "Attribute" && targets[0].RootName() == name
	})
}

// ArgumentIsReadAfterTheCall says whether the name the call hands over at the position is read again later in
// the def around it. The value the callee wrote into it is what the caller came back for, which is the whole
// shape of a filled parameter.
func (n Node) ArgumentIsReadAfterTheCall(at int) bool {
	arguments := n.Arguments()
	caller := n.EnclosingFunction()
	if at < 0 || at >= len(arguments) || !caller.Exists() {
		return false
	}
	handed := arguments[at]
	if handed.Kind() != "Name" {
		return false
	}

	return slices.ContainsFunc(caller.Descendants(), func(node Node) bool {
		return node.readsTheNameAfter(handed) && node.sharesABlockWith(handed)
	})
}

// sharesABlockWith says whether the two nodes' own statements sit in the same block, so one really does run
// after the other. A read in another branch may be unreachable from the call, and holding a def to that would
// be guessing at the flow rather than reading it.
func (n Node) sharesABlockWith(other Node) bool {
	mine, theirs := n.ownStatement(), other.ownStatement()

	return mine.Exists() && theirs.Exists() && mine.Parent().Node() == theirs.Parent().Node() && mine.Node().Field == theirs.Node().Field
}

// ownStatement is the node itself or the ancestor of it that a block holds directly.
func (n Node) ownStatement() Node {
	for held := n; held.Exists(); held = held.Parent() {
		if slices.Contains([]string{"body", "orelse", "finalbody"}, held.Node().Field) {
			return held
		}
	}

	return Node{}
}

// readsTheNameAfter says whether the node reads what the callee wrote into the handed-over name, later in the
// source: an attribute of it, or the name handed back. Passing it on to something else is not the caller coming
// back for a value.
func (n Node) readsTheNameAfter(handed Node) bool {
	if n.Kind() != "Name" || n.Name() != handed.Name() || n.Node().Span.Start <= handed.Node().Span.Start {
		return false
	}
	parent := n.Parent()

	return parent.Kind() == "Attribute" || parent.Kind() == "Return"
}

// ParameterPosition is where the named parameter sits in the def's list, counting from zero and counting `self`,
// since a call on an instance fills the rest in that order; -1 for a name it does not declare.
func (n Node) ParameterPosition(name string) int {
	taken := 0
	for _, parameter := range n.Parameters() {
		if parameter.Name() == "self" {
			continue
		}
		if parameter.Name() == name {
			return taken
		}
		taken++
	}

	return -1
}

// mutableCollections are the annotations that say "a collection anyone may change": returning one of a class's
// own is handing the insides out. A tuple, a frozenset and the Sequence, Mapping and AbstractSet protocols say
// the opposite and are how a getter gives its collection away safely.
var mutableCollections = []string{"list", "dict", "set", "bytearray", "List", "Dict", "Set", "typing.List", "typing.Dict", "typing.Set", "MutableSequence", "MutableMapping", "MutableSet", "typing.MutableSequence", "typing.MutableMapping", "typing.MutableSet", "collections.defaultdict", "collections.OrderedDict", "collections.Counter", "collections.deque", "defaultdict", "OrderedDict", "Counter", "deque"}

// HandsOutItsOwnCollection says whether the def is a getter whose whole body returns one of its class's own
// mutable collections, untouched: every caller then holds the class's insides and may add to or take from them
// without asking, so the class cannot keep its own invariant and does not know when it is broken.
func (n Node) HandsOutItsOwnCollection() bool {
	body := n.StatementsBeyondText()
	if !n.IsMethod() || len(body) != 1 || body[0].Kind() != "Return" {
		return false
	}
	if !slices.Contains(mutableCollections, n.Child("returns").CollectionName()) {
		return false
	}
	returned := body[0].Child("value")

	return returned.Kind() == "Attribute" && returned.RootName() == "self" && n.EnclosingClass().keepsACollectionIn(returned.Name())
}

// CollectionName is the name the annotation's outermost type goes by, its arguments aside: `list` for
// `list[str]`, `dict` for `dict[str, int]`, `MutableSequence` for `MutableSequence[Order]`.
func (n Node) CollectionName() string {
	if n.Kind() == "Subscript" {
		return n.Child("value").DottedName()
	}

	return n.DottedName()
}

// keepsACollectionIn says whether the class's __init__ puts a collection in the attribute: a list, dict or set
// written out or comprehended, or one a call to a builtin that makes one builds.
func (n Node) keepsACollectionIn(name string) bool {
	init := n.Initializer()

	return slices.ContainsFunc(init.Descendants(), func(node Node) bool {
		targets := node.ChildrenIn("targets")
		if node.Kind() == "AnnAssign" {
			return node.Child("target").Name() == name && slices.Contains(mutableCollections, node.Child("annotation").CollectionName())
		}

		return node.Kind() == "Assign" && len(targets) == 1 && targets[0].Name() == name && node.Child("value").buildsAFreshContainer()
	})
}
