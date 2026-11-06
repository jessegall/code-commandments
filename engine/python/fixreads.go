package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

var (
	genericExceptions = []string{"Exception", "BaseException", "RuntimeError"}
	rootExceptions    = []string{"Exception", "BaseException"}
)

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

func (n Node) IsRaiseWithoutCause() bool {
	raised := n.Child("exc")
	if n.Kind() != "Raise" || !raised.Exists() || n.Child("cause").Exists() {
		return false
	}
	handler := n.enclosingHandler()
	dotted := raised.DottedName()

	return handler.Exists() && (handler.Name() == "" || dotted != handler.Name()) && !handler.setsCauseOf(dotted)
}

func (n Node) enclosingHandler() Node {
	for around := n.Parent(); around.Exists() && !around.IsDefinition(); around = around.Parent() {
		if around.Kind() == "ExceptHandler" {
			return around
		}
	}

	return Node{}
}

func (n Node) setsCauseOf(raised string) bool {
	return raised != "" && slices.ContainsFunc(n.Descendants(), func(assign Node) bool {
		return assign.Kind() == "Assign" && slices.ContainsFunc(assign.ChildrenIn("targets"), func(target Node) bool { return target.DottedName() == raised+".__cause__" })
	})
}

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

func (n Node) Swallows() bool {
	body := n.ChildrenIn("body")

	return len(body) == 1 && body[0].isNoOp()
}

func (n Node) ConstructorHasSideEffect() bool {
	init := n.Initializer()

	return n.Kind() == "ClassDef" && init.Exists() && slices.ContainsFunc(init.ExpressionsIn(), func(expression Node) bool {
		return expression.ResultIsDiscarded() && !expression.isProbed() && expression.actsOnCollaborator(init)
	})
}

func (n Node) owner() Node {
	around := n.Parent()
	for around.Exists() && !around.IsStatement() {
		around = around.Parent()
	}

	return around
}

func (n Node) isProbed() bool {
	for statement := n.owner(); statement.Exists() && !statement.IsDefinition(); statement = statement.Parent() {
		try := statement.Parent()
		if (try.Kind() == "Try" || try.Kind() == "TryStar") && len(try.ChildrenIn("handlers")) > 0 && statement.Node().Field == "body" {
			return true
		}
	}

	return false
}

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

func (n Node) IsStaticStateWrite() bool {
	function := n.EnclosingFunction()
	if !function.Exists() {
		return false
	}
	if n.changesAContainerTheClassHolds() {
		return true
	}

	return slices.ContainsFunc(n.writtenTargets(), func(target Node) bool { return n.writesStatic(target, function) })
}

func (n Node) changesAContainerTheClassHolds() bool {
	changing := n
	if n.Kind() == "Expr" {
		changing = n.Child("value")
	}
	changed, changes := changing.collectionItChanges()
	class := n.EnclosingClass()
	if !changes || changed.Kind() != "Attribute" || changed.RootName() != "self" {
		return false
	}
	if !class.declaresAContainerNamed(changed.Name()) {
		return false
	}

	return !class.everAssignsTheAttribute(changed.Name())
}

func (n Node) declaresAContainerNamed(name string) bool {
	return slices.ContainsFunc(n.ChildrenIn("body"), func(member Node) bool {
		targets := member.ChildrenIn("targets")
		if member.Kind() == "AnnAssign" {
			return member.Child("target").Name() == name && member.Child("value").buildsAFreshContainer()
		}

		return member.Kind() == "Assign" && len(targets) == 1 && targets[0].Name() == name && member.Child("value").buildsAFreshContainer()
	})
}

func (n Node) everAssignsTheAttribute(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(node Node) bool {
		targets := node.ChildrenIn("targets")
		if node.Kind() != "Assign" || len(targets) != 1 {
			return false
		}

		return targets[0].Kind() == "Attribute" && targets[0].RootName() == "self" && targets[0].Name() == name
	})
}

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

func (n Node) globalNames() []string {
	var names []string
	for _, statement := range n.statementsIn() {
		if extras := statement.Node().Extras; statement.Kind() == "Global" && statement.EnclosingFunction() == n && extras != nil && extras.Python != nil {
			names = append(names, extras.Python.Names...)
		}
	}

	return names
}

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

func (n Node) MissesToNone() bool {
	callee, arguments := n.Callee(), n.Arguments()
	count := len(arguments) + len(n.Keywords())

	return n.IsCall() && callee.Kind() == "Attribute" && callee.Name() == "get" && (count == 1 || (count == 2 && len(arguments) == 2 && arguments[1].IsNone()))
}

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

func (n Node) isTheLastClause() bool {
	clauses := n.Parent().ChildrenIn("handlers")

	return len(clauses) > 0 && clauses[len(clauses)-1].Node() == n.Node()
}

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

func (n Node) namesAClass() bool {
	named := n.DottedName()

	return named != "" && !slices.Contains([]string{"str", "int", "float", "bool", "bytes", "list", "dict", "set", "tuple", "frozenset", "Any", "typing.Any", "object"}, named)
}

func (n Node) ReadsItsAttributesWithoutNamingThem() bool {
	for _, method := range n.Methods() {
		if slices.Contains([]string{"__getattr__", "__getattribute__", "__setattr__", "__reduce__", "__getstate__"}, method.Name()) {
			return true
		}
	}

	return slices.ContainsFunc(n.Descendants(), readsEveryAttribute)
}

func readsEveryAttribute(n Node) bool {
	if n.Kind() == "Attribute" && n.Name() == "__dict__" {
		return true
	}

	return n.IsCall() && slices.Contains([]string{"vars", "dataclasses.asdict", "dataclasses.astuple"}, n.Callee().DottedName())
}

func readsTheAttribute(codebase *engine.Codebase, name string) bool {
	return In(codebase).
		WhereKind("Attribute").
		Where(func(node engine.Match) bool { return node.Name() == name }).
		Reject(func(node engine.Match) bool { return Node{Match: node}.isAssigned() }).
		Count() > 0
}

func (n Node) isAssigned() bool {
	return n.Node().Field == "targets"
}

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

func (n Node) writesAnAttributeOf(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(node Node) bool {
		targets := node.ChildrenIn("targets")

		return node.Kind() == "Assign" && len(targets) == 1 && targets[0].Kind() == "Attribute" && targets[0].RootName() == name
	})
}

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

func (n Node) sharesABlockWith(other Node) bool {
	mine, theirs := n.ownStatement(), other.ownStatement()

	return mine.Exists() && theirs.Exists() && mine.Parent().Node() == theirs.Parent().Node() && mine.Node().Field == theirs.Node().Field
}

func (n Node) ownStatement() Node {
	for held := n; held.Exists(); held = held.Parent() {
		if slices.Contains([]string{"body", "orelse", "finalbody"}, held.Node().Field) {
			return held
		}
	}

	return Node{}
}

func (n Node) readsTheNameAfter(handed Node) bool {
	if n.Kind() != "Name" || n.Name() != handed.Name() || n.Node().Span.Start <= handed.Node().Span.Start {
		return false
	}
	parent := n.Parent()

	return parent.Kind() == "Attribute" || parent.Kind() == "Return"
}

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

var mutableCollections = []string{"list", "dict", "set", "bytearray", "List", "Dict", "Set", "typing.List", "typing.Dict", "typing.Set", "MutableSequence", "MutableMapping", "MutableSet", "typing.MutableSequence", "typing.MutableMapping", "typing.MutableSet", "collections.defaultdict", "collections.OrderedDict", "collections.Counter", "collections.deque", "defaultdict", "OrderedDict", "Counter", "deque"}

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

func (n Node) CollectionName() string {
	if n.Kind() == "Subscript" {
		return n.Child("value").DottedName()
	}

	return n.DottedName()
}

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

var blockingCalls = []string{
	"time.sleep", "requests.get", "requests.post", "requests.put", "requests.patch", "requests.delete",
	"requests.head", "requests.request", "urllib.request.urlopen", "subprocess.run", "subprocess.call",
	"subprocess.check_call", "subprocess.check_output", "os.system", "socket.socket.recv", "input",
}

func (n Node) BlocksTheEventLoop() bool {
	if !n.IsCall() || !slices.Contains(blockingCalls, n.Callee().DottedName()) {
		return false
	}
	def := n.EnclosingFunction()

	return def.Kind() == "AsyncFunctionDef" && !n.handedToAThread()
}

func (n Node) handedToAThread() bool {
	for held := n.Parent(); held.Exists(); held = held.Parent() {
		if held.IsCall() && slices.Contains([]string{"asyncio.to_thread", "to_thread", "run_in_executor"}, held.Callee().Name()) {
			return true
		}
	}

	return false
}
