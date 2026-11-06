package python

import (
	"slices"
	"strconv"
	"strings"
)

func (n Node) Default() Node {
	return n.Child("default")
}

func (n Node) IsBlankString() bool {
	text, ok := n.Text()

	return ok && text == ""
}

func (n Node) IsEmptyScalar() bool {
	facts := n.Node()
	if n.Kind() != "Constant" {
		return false
	}
	switch facts.Literal {
	case "string", "bytes":
		return n.IsBlankString() || (facts.Literal == "bytes" && (n.Written() == `b""` || n.Written() == `b''`))
	case "int", "float":
		number, err := strconv.ParseFloat(n.Written(), 64)

		return err == nil && number == 0
	case "bool":
		return n.Written() == "False"
	}

	return false
}

func (n Node) OwnerDef() Node {
	if def := n.Parent().Parent(); n.Kind() == "arg" && def.IsFunction() {
		return def
	}

	return Node{}
}

func (n Node) blankDefault() (Node, string, bool) {
	if def := n.OwnerDef(); def.Exists() && n.Child("annotation").DottedName() == "str" && n.Default().IsBlankString() {
		return def, n.Name(), true
	}
	target, class := n.Child("target"), n.Parent()
	if n.Kind() == "AnnAssign" && class.Kind() == "ClassDef" && target.Kind() == "Name" && n.Child("annotation").DottedName() == "str" && n.Child("value").IsBlankString() {
		return class, "self." + target.Name(), true
	}

	return Node{}, "", false
}

func (n Node) IsBlankStringDefault() bool {
	_, _, ok := n.blankDefault()

	return ok
}

func (n Node) DefaultedNameTestedForBlankness() bool {
	scope, dotted, ok := n.blankDefault()

	return ok && scope.AsksBlanknessOf(dotted)
}

func (n Node) AsksBlanknessOf(dotted string) bool {
	return slices.ContainsFunc(n.ExpressionsIn(), func(expression Node) bool {
		return expression.testsBlanknessOf(dotted) || (expression.DottedName() == dotted && expression.isTested())
	})
}

func (n Node) AsksAbsenceOf(dotted string) bool {
	return slices.ContainsFunc(n.ExpressionsIn(), func(expression Node) bool {
		subject, falls := expression.FallbackSubject()

		return expression.testsNoneOf(dotted) || (expression.DottedName() == dotted && expression.isTested()) || (falls && subject.DottedName() == dotted)
	})
}

func (n Node) testsBlanknessOf(dotted string) bool {
	if n.IsNegation() {
		return n.Child("operand").DottedName() == dotted
	}
	left, right, ok := n.comparedWith("==", "!=")

	return ok && ((left.DottedName() == dotted && right.IsBlankString()) || (right.DottedName() == dotted && left.IsBlankString()))
}

func (n Node) testsNoneOf(dotted string) bool {
	left, right, ok := n.comparedWith("is", "is not")

	return ok && ((left.DottedName() == dotted && right.IsNone()) || (right.DottedName() == dotted && left.IsNone()))
}

func (n Node) comparedWith(operators ...string) (Node, Node, bool) {
	comparators := n.ChildrenIn("comparators")
	if n.Kind() != "Compare" || len(comparators) != 1 || !slices.Contains(operators, n.Node().Operator) {
		return Node{}, Node{}, false
	}

	return n.Child("left"), comparators[0], true
}

func (p *Program) IsFieldOfDataBuiltClass(n Node) bool {
	return n.Kind() == "AnnAssign" && n.Parent().Kind() == "ClassDef" && p.IsBuiltFromData(n.Parent())
}

func (p *Program) IsParameterOfADispatchedMethod(n Node) bool {
	method := n.OwnerDef()
	class, bound := method.BoundClass()

	return method.Exists() && !strings.HasPrefix(method.Name(), "_") && bound && p.IsDispatchedByName(class)
}

func (p *Program) IsParameterEveryCallFills(n Node) bool {
	def := n.OwnerDef()
	calls := p.CallersOf(def)

	return def.Exists() && len(calls) > 0 && !slices.ContainsFunc(calls, func(call Node) bool {
		bound, ok := p.ArgumentsAt(call)
		_, fills := bound[n.Name()]

		return !ok || !fills
	})
}

func (n Node) IsComparedToItsFallback() bool {
	fallback, ok := n.Fallback()
	left, right, compared := n.wrapper().comparedWith("==", "!=")

	return ok && compared && ((left == n && right.IsSame(fallback)) || (right == n && left.IsSame(fallback)))
}

func (n Node) IsConditionalSpread() bool {
	choice := n.spreadValue()
	if choice.Kind() != "IfExp" {
		return false
	}
	then, otherwise := choice.Child("body"), choice.Child("orelse")

	return (then.IsEmptyCollection() && otherwise.isDisplay()) || (otherwise.IsEmptyCollection() && then.isDisplay())
}

func (n Node) spreadValue() Node {
	switch {
	case n.Kind() == "Starred" || (n.Kind() == "keyword" && n.Name() == ""):
		return n.Child("value")
	case slices.Contains(n.Node().Flags, "spread"):
		return n
	}

	return Node{}
}

func (n Node) isDisplay() bool {
	return slices.Contains([]string{"Tuple", "List", "Set", "Dict"}, n.Kind())
}

func (n Node) FillsArgument() bool {
	wrapper := n.wrapper()
	if wrapper.Kind() == "keyword" {
		wrapper = wrapper.Parent()
	}

	return wrapper.IsCall() && wrapper.Callee() != n
}

func (n Node) IsKeyedDefault() bool {
	_, _, ok := n.defaulted()

	return ok && n.IsCall()
}

func (n Node) ReturnedValue() Node {
	if n.Kind() != "Return" {
		return Node{}
	}

	return n.Child("value")
}

func (n Node) IsInventingOnMiss() bool {
	var empty, found []Node
	for _, value := range n.ReturnedValues() {
		if value.IsEmptyScalar() {
			empty = append(empty, value)
		} else {
			found = append(found, value)
		}
	}
	if len(empty) == 0 || len(found) == 0 {
		return false
	}

	if !slices.ContainsFunc(found, func(value Node) bool { return !value.IsBooleanAnswer() }) {
		return false
	}

	return !slices.ContainsFunc(found, func(value Node) bool { return !n.isLookedUp(value) })
}

func (n Node) IsBooleanAnswer() bool {
	switch n.Kind() {
	case "Compare":
		return true
	case "UnaryOp":
		return n.Node().Operator == "not"
	case "BoolOp":
		parts := n.ChildrenIn("values")

		return len(parts) > 0 && !slices.ContainsFunc(parts, func(part Node) bool { return !part.IsBooleanAnswer() })
	case "Constant":
		return n.Node().Literal == "bool"
	}

	return false
}

func (n Node) isLookedUp(value Node) bool {
	var locals []string
	for _, statement := range n.statementsIn() {
		targets := statement.ChildrenIn("targets")
		if statement.Kind() == "Assign" && len(targets) == 1 && targets[0].Kind() == "Name" && slices.ContainsFunc(statement.ownEvaluated(), n.isLookup) {
			locals = append(locals, targets[0].Name())
		}
	}

	return slices.ContainsFunc(value.evaluatedIn(), func(part Node) bool {
		return n.isLookup(part) || (part.Kind() == "Name" && slices.Contains(locals, part.Name()))
	})
}

func (n Node) ownEvaluated() []Node {
	var evaluated []Node
	for _, expression := range n.ownExpressions() {
		evaluated = append(evaluated, expression.evaluatedIn()...)
	}

	return evaluated
}

func (n Node) isLookup(part Node) bool {
	names := parameterNames(n.Parameters())
	key, ok := part.KeyReadOf(names)
	if !ok {
		return false
	}
	_, isText := key.Text()
	_, isLoop := n.keyLoops()[key.DottedName()]

	return part.IsCall() || isText || slices.Contains(names, key.DottedName()) || isLoop
}

func (n Node) keyLoops() map[string]string {
	names := parameterNames(n.Parameters())
	loops := map[string]string{}
	for _, statement := range n.statementsIn() {
		target, iterable := statement.Child("target"), statement.Child("iter")
		if (statement.Kind() == "For" || statement.Kind() == "AsyncFor") && target.Kind() == "Name" && slices.Contains(names, iterable.DottedName()) {
			loops[target.Name()] = iterable.DottedName()
		}
	}

	return loops
}

var callableTypes = []string{"Callable", "typing.Callable", "collections.abc.Callable"}

func (n Node) HasNullNormalisedOptionalCallback() bool {
	return n.IsFunction() && slices.ContainsFunc(n.Parameters(), func(parameter Node) bool {
		callable, optional := parameter.Child("annotation").OptionalOf()

		return optional && callable.isCallableType() && parameter.Default().IsNone() && n.AsksAbsenceOf(parameter.Name())
	})
}

func (n Node) isCallableType() bool {
	named := n
	if n.Kind() == "Subscript" {
		named = n.Child("value")
	}

	return slices.Contains(callableTypes, named.DottedName())
}

func (p *Program) IsConstantProperty(n Node) bool {
	if !n.IsPropertyGetter() || len(n.Parameters()) == 0 || n.IsStub() || p.IsOverride(n) || p.IsOverridden(n) || p.ExtendsOutside(n) {
		return false
	}
	if class := n.Parent(); class.Kind() == "ClassDef" && slices.ContainsFunc(class.Methods(), func(member Node) bool { return member.IsPropertyAccessorOf(n.Name()) }) {
		return false
	}
	body := n.StatementsBeyondText()

	return len(body) == 1 && body[0].ReturnedValue().Exists() && len(body[0].ReturnedValue().DataNames()) == 0
}

func (n Node) IsPropertyAccessorOf(name string) bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		return decorator.DottedName() == name+".setter" || decorator.DottedName() == name+".deleter"
	})
}

func (n Node) IsStub() bool {
	return n.IsFunction() && !slices.ContainsFunc(n.ChildrenIn("body"), func(statement Node) bool { return !statement.IsPlaceholder() })
}

func (n Node) IsPlaceholder() bool {
	switch n.Kind() {
	case "Pass":
		return true
	case "Expr":
		value := n.Child("value")
		_, isText := value.Text()

		return isText || value.Node().Literal == "ellipsis"
	case "Raise":
		raised := n.Child("exc")
		if raised.IsCall() {
			raised = raised.Callee()
		}

		return raised.Kind() == "Name" && raised.Name() == "NotImplementedError"
	}

	return false
}

func (n Node) DataNames() []string {
	if n.Kind() == "Name" {
		return []string{n.Name()}
	}
	parts := n.Children()
	if n.IsCall() && n.Callee().Kind() == "Name" {
		parts = append(n.Arguments(), n.Keywords()...)
	}
	bound := n.boundNames()
	var read []string
	for _, part := range parts {
		for _, name := range part.DataNames() {
			if !slices.Contains(bound, name) {
				read = append(read, name)
			}
		}
	}

	return read
}

func (n Node) boundNames() []string {
	var bound []string
	for _, clause := range n.ChildrenIn("generators") {
		target := clause.Child("target")
		for _, name := range append([]Node{target}, target.Descendants()...) {
			if name.Kind() == "Name" {
				bound = append(bound, name.Name())
			}
		}
	}

	return bound
}

func (n Node) ResetsToNone(name string) bool {
	if slices.ContainsFunc(n.writesOutsideInit(name), func(write Node) bool { return write.Kind() == "Assign" && write.Child("value").IsNone() }) {
		return true
	}

	return slices.ContainsFunc(n.Methods(), func(method Node) bool { return method.handsNoneFor(name) })
}

func (n Node) handsNoneFor(name string) bool {
	for _, node := range n.Match.Descendants() {
		call := Node{Match: node}
		if call.Kind() != "Call" {
			continue
		}

		if value := call.Keyword(name); value.Exists() && value.IsNone() {
			return true
		}
	}

	return false
}

func (n Node) writesOutsideInit(name string) []Node {
	var writes []Node
	for _, method := range n.Methods() {
		if method.Name() == "__init__" {
			continue
		}
		for _, statement := range method.statementsIn() {
			if slices.ContainsFunc(statement.writtenTargets(), func(target Node) bool { return target.SelfAttribute() == name }) {
				writes = append(writes, statement)
			}
		}
	}

	return writes
}

func (n Node) FieldDeclaration(name string) Node {
	for _, statement := range n.ChildrenIn("body") {
		if statement.Kind() == "AnnAssign" && statement.Child("target").DottedName() == name {
			return statement
		}
	}
	if init := n.Initializer(); init.Exists() {
		for _, statement := range init.statementsIn() {
			if slices.ContainsFunc(statement.writtenTargets(), func(target Node) bool { return target.SelfAttribute() == name }) {
				return statement
			}
		}
	}

	return Node{}
}

func (p *Program) FillsRequiredTextWithBlank(call Node) bool {
	class, ok := p.Dataclass(call.Callee())
	if !call.IsCall() || !ok {
		return false
	}
	required := class.RequiredTextFields()

	return slices.ContainsFunc(call.fieldsHandedBlank(class.InitFieldNames()), func(field string) bool { return slices.Contains(required, field) })
}

func (n Node) fieldsHandedBlank(fields []string) []string {
	var blank []string
	for position, argument := range n.Arguments() {
		if position < len(fields) && argument.IsBlankString() {
			blank = append(blank, fields[position])
		}
	}
	for _, keyword := range n.Keywords() {
		if keyword.Child("value").IsBlankString() {
			blank = append(blank, keyword.Name())
		}
	}

	return blank
}

func (n Node) RequiredTextFields() []string {
	var fields []string
	for _, statement := range n.ChildrenIn("body") {
		target := statement.Child("target")
		if statement.Kind() == "AnnAssign" && target.Kind() == "Name" && !statement.Child("value").Exists() && statement.Child("annotation").DottedName() == "str" {
			fields = append(fields, target.Name())
		}
	}

	return fields
}

func (n Node) InitFieldNames() []string {
	var fields []string
	for _, statement := range n.ChildrenIn("body") {
		target := statement.Child("target")
		if statement.Kind() == "AnnAssign" && target.Kind() == "Name" && !statement.Child("annotation").IsClassVarType() && !statement.Child("value").isKeptOutOfInit() {
			fields = append(fields, target.Name())
		}
	}

	return fields
}

func (n Node) isKeptOutOfInit() bool {
	if !n.IsCall() || n.Callee().DottedName() != "field" {
		return false
	}
	init := n.Keyword("init")

	return init.Node() != nil && init.Written() == "False"
}

func (n Node) IsContextManager() bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		return slices.Contains([]string{"contextmanager", "contextlib.contextmanager", "asynccontextmanager", "contextlib.asynccontextmanager"}, decorator.DottedName())
	})
}

func (n Node) HasOwnStateSaveAndRestore() bool {
	if !n.IsFunction() || n.IsContextManager() {
		return false
	}
	writes := slices.DeleteFunc(n.statementsIn(), func(statement Node) bool { return len(statement.writtenTargets()) == 0 })
	counts := map[string]int{}
	for _, write := range writes {
		for _, name := range write.writtenNames() {
			counts[name]++
		}
	}
	parameters := parameterNames(n.Parameters())
	saved := map[string]string{}
	for _, write := range writes {
		if write.Kind() != "Assign" {
			continue
		}
		target, value := write.ChildrenIn("targets")[0], write.Child("value")
		if target.Kind() == "Name" && value.Kind() == "Attribute" && value.RootName() == "self" && value.DottedName() != "" && counts[target.Name()] == 1 && !slices.Contains(parameters, target.Name()) {
			saved[target.Name()] = value.DottedName()
		}
	}

	return slices.ContainsFunc(writes, func(write Node) bool {
		value := write.Child("value")
		restored, ok := saved[value.Name()]

		return write.Kind() == "Assign" && value.Kind() == "Name" && ok && restored == write.ChildrenIn("targets")[0].DottedName()
	})
}

var mutableBuilders = []string{"list", "dict", "set", "bytearray", "collections.defaultdict", "collections.OrderedDict", "collections.Counter", "collections.deque"}

var freshEveryCall = []string{
	"datetime.now", "datetime.utcnow", "datetime.today", "datetime.datetime.now", "datetime.datetime.utcnow",
	"datetime.datetime.today", "date.today", "datetime.date.today", "time.time", "time.monotonic",
	"time.perf_counter", "uuid.uuid1", "uuid.uuid4", "uuid1", "uuid4", "os.urandom", "random.random",
	"random.randint", "random.choice", "secrets.token_hex", "secrets.token_urlsafe",
}

func (n Node) IsMutableDefault() bool {
	if !n.OwnerDef().Exists() || n.Kind() != "arg" {
		return false
	}

	return n.Default().buildsAFreshContainer()
}

func (n Node) IsFrozenDefault() bool {
	if !n.OwnerDef().Exists() || n.Kind() != "arg" {
		return false
	}
	defaulted := n.Default()

	return defaulted.IsCall() && slices.Contains(freshEveryCall, defaulted.Callee().DottedName())
}

func (n Node) DeclaresNoBody() bool {
	return n.OwnerDef().IsStub()
}

func (n Node) buildsAFreshContainer() bool {
	if slices.Contains([]string{"List", "Dict", "Set", "ListComp", "DictComp", "SetComp"}, n.Kind()) {
		return true
	}

	return n.IsCall() && slices.Contains(mutableBuilders, n.Callee().DottedName())
}

func (p *Program) GuardsWhatTheTypeRulesOut(n Node) bool {
	tested, guards := n.testedForNone()
	def := n.EnclosingFunction()
	if !guards || !def.Exists() {
		return false
	}
	annotation, declared := def.annotationOf(tested)
	if !declared || !p.DeclaresClass(annotation.DottedName()) {
		return false
	}
	if _, optional := annotation.OptionalOf(); optional {
		return false
	}

	if n.onlyFailsFast() {
		return false
	}

	return !def.everPutsNoneIn(tested)
}

func (n Node) onlyFailsFast() bool {
	guard := n.Parent()
	body := guard.ChildrenIn("body")

	return guard.Kind() == "If" && len(body) == 1 && body[0].Kind() == "Raise"
}

func (n Node) testedForNone() (string, bool) {
	left, right, ok := n.comparedWith("is", "is not")
	if !ok {
		return "", false
	}
	if left.Kind() == "Name" && right.IsNone() {
		return left.Name(), true
	}
	if right.Kind() == "Name" && left.IsNone() {
		return right.Name(), true
	}

	return "", false
}

func (n Node) annotationOf(name string) (Node, bool) {
	for _, parameter := range n.Parameters() {
		if parameter.Name() != name {
			continue
		}
		annotation := parameter.Child("annotation")

		return annotation, annotation.Exists() && !parameter.Default().IsNone()
	}

	return Node{}, false
}

func (n Node) everPutsNoneIn(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(node Node) bool {
		targets := node.ChildrenIn("targets")

		return node.Kind() == "Assign" && len(targets) == 1 && targets[0].Name() == name && node.Child("value").IsNone()
	})
}

const matched = "re.Match"

func (n Node) FallsBackOnAGroupOfAMatch() bool {
	subject, ok := n.FallbackSubject()
	if !ok {
		return false
	}

	class, typed := subject.heldBy().ResolvedClass()

	return typed && class == matched
}

func (n Node) heldBy() Node {
	if n.IsCall() {
		return n.Callee().Child("value")
	}

	return n.Child("value")
}
