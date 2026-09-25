package python

import (
	"slices"
	"strconv"
	"strings"
)

// Default is the default value a parameter is given; no node when it has none.
func (n Node) Default() Node {
	return n.Child("default")
}

// IsBlankString says whether the expression is the literal empty string.
func (n Node) IsBlankString() bool {
	text, ok := n.Text()

	return ok && text == ""
}

// IsEmptyScalar says whether the expression is a literal that means nothing: an empty string, zero or False.
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

// OwnerDef is the def a parameter belongs to; no node for anything else.
func (n Node) OwnerDef() Node {
	if def := n.Parent().Parent(); n.Kind() == "arg" && def.IsFunction() {
		return def
	}

	return Node{}
}

// blankDefault is where a text slot defaulted to "" lives and how it is read there: a `str` parameter's def and
// its name, or a class's `str` field and `self.<field>`.
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

// IsBlankStringDefault says whether the node is a `str` parameter or field defaulted to "".
func (n Node) IsBlankStringDefault() bool {
	_, _, ok := n.blankDefault()

	return ok
}

// DefaultedNameTestedForBlankness says whether the code the blank default belongs to asks whether it is blank:
// the default stands in for a value that may be missing.
func (n Node) DefaultedNameTestedForBlankness() bool {
	scope, dotted, ok := n.blankDefault()

	return ok && scope.AsksBlanknessOf(dotted)
}

// AsksBlanknessOf says whether anything the scope evaluates asks whether the dotted name is blank: compares it to
// "", negates it, or tests it bare as the condition of an if, a while or a conditional expression.
func (n Node) AsksBlanknessOf(dotted string) bool {
	return slices.ContainsFunc(n.ExpressionsIn(), func(expression Node) bool {
		return expression.testsBlanknessOf(dotted) || (expression.DottedName() == dotted && expression.isTested())
	})
}

// AsksAbsenceOf says whether anything the scope evaluates asks whether the dotted name is missing: compares it to
// None, tests it bare, or falls back from it.
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

// comparedWith is the two sides of a comparison made with one of the operators and nothing else.
func (n Node) comparedWith(operators ...string) (Node, Node, bool) {
	comparators := n.ChildrenIn("comparators")
	if n.Kind() != "Compare" || len(comparators) != 1 || !slices.Contains(operators, n.Node().Operator) {
		return Node{}, Node{}, false
	}

	return n.Child("left"), comparators[0], true
}

// IsFieldOfDataBuiltClass says whether the node is a field of a class a named constructor builds from data.
func (p *Program) IsFieldOfDataBuiltClass(n Node) bool {
	return n.Kind() == "AnnAssign" && n.Parent().Kind() == "ClassDef" && p.IsBuiltFromData(n.Parent())
}

// IsParameterOfADispatchedMethod says whether the node is a parameter of a public method a getattr dispatches to
// by a run-time name: the boundary hands it whatever it has.
func (p *Program) IsParameterOfADispatchedMethod(n Node) bool {
	method := n.OwnerDef()
	class, bound := method.BoundClass()

	return method.Exists() && !strings.HasPrefix(method.Name(), "_") && bound && p.IsDispatchedByName(class)
}

// IsParameterEveryCallFills says whether every call of the parameter's def hands it a value.
func (p *Program) IsParameterEveryCallFills(n Node) bool {
	def := n.OwnerDef()
	calls := p.CallersOf(def)

	return def.Exists() && len(calls) > 0 && !slices.ContainsFunc(calls, func(call Node) bool {
		bound, ok := p.ArgumentsAt(call)
		_, fills := bound[n.Name()]

		return !ok || !fills
	})
}

// IsComparedToItsFallback says whether the expression is compared, right where it stands, to the value it falls
// back to: the fallback is cancelled by the very next test.
func (n Node) IsComparedToItsFallback() bool {
	fallback, ok := n.Fallback()
	left, right, compared := n.wrapper().comparedWith("==", "!=")

	return ok && compared && ((left == n && right.IsSame(fallback)) || (right == n && left.IsSame(fallback)))
}

// IsConditionalSpread says whether the node spreads a conditional choosing between a collection and an empty one:
// `*(items if wanted else [])`.
func (n Node) IsConditionalSpread() bool {
	choice := n.spreadValue()
	if choice.Kind() != "IfExp" {
		return false
	}
	then, otherwise := choice.Child("body"), choice.Child("orelse")

	return (then.IsEmptyCollection() && otherwise.isDisplay()) || (otherwise.IsEmptyCollection() && then.isDisplay())
}

// spreadValue is what a spread unpacks: a starred value, a `**` argument's, or a `**` dict entry itself.
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

// FillsArgument says whether the expression is handed to a call as an argument, by position or by name.
func (n Node) FillsArgument() bool {
	wrapper := n.wrapper()
	if wrapper.Kind() == "keyword" {
		wrapper = wrapper.Parent()
	}

	return wrapper.IsCall() && wrapper.Callee() != n
}

// IsKeyedDefault says whether the expression is a mapping lookup naming its own default: `m.get(k, d)`.
func (n Node) IsKeyedDefault() bool {
	_, _, ok := n.defaulted()

	return ok && n.IsCall()
}

// ReturnedValue is what a return statement returns; no node for any other statement.
func (n Node) ReturnedValue() Node {
	if n.Kind() != "Return" {
		return Node{}
	}

	return n.Child("value")
}

// IsInventingOnMiss says whether the def answers a lookup it failed with a blank literal: some returns hand back
// what it looked up, the rest an empty string, zero or False standing in for "not found".
func (n Node) IsInventingOnMiss() bool {
	var empty, found []Node
	for _, value := range n.ReturnedValues() {
		if value.IsEmptyScalar() {
			empty = append(empty, value)
		} else {
			found = append(found, value)
		}
	}

	return len(empty) > 0 && len(found) > 0 && !slices.ContainsFunc(found, func(value Node) bool { return !n.isLookedUp(value) })
}

// isLookedUp says whether the returned value is a lookup the def makes, or a local it assigned one to.
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

// ownEvaluated is every expression a statement's own head evaluates, the statements it holds aside.
func (n Node) ownEvaluated() []Node {
	var evaluated []Node
	for _, expression := range n.ownExpressions() {
		evaluated = append(evaluated, expression.evaluatedIn()...)
	}

	return evaluated
}

// isLookup says whether the part reads one of the def's parameters by a key: a call such as `row.get(key)`, or a
// subscript by a literal, a parameter or a loop variable over one.
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

// keyLoops is each loop variable of the def that walks one of its parameters, with the parameter it walks.
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

// callableTypes is how an annotation spells a callable.
var callableTypes = []string{"Callable", "typing.Callable", "collections.abc.Callable"}

// HasNullNormalisedOptionalCallback says whether the def takes an optional callable defaulted to None and then asks
// whether it was given: a missing behaviour modelled as None rather than as a behaviour that does nothing.
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

// IsConstantProperty says whether the method is a property that returns the same literal answer every time and
// keeps no contract: a constant written as a method.
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

// IsPropertyAccessorOf says whether the def is the setter or deleter of the named property.
func (n Node) IsPropertyAccessorOf(name string) bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		return decorator.DottedName() == name+".setter" || decorator.DottedName() == name+".deleter"
	})
}

// IsStub says whether every statement of the def's body is a placeholder: nothing to share.
func (n Node) IsStub() bool {
	return n.IsFunction() && !slices.ContainsFunc(n.ChildrenIn("body"), func(statement Node) bool { return !statement.IsPlaceholder() })
}

// IsPlaceholder says whether the statement stands in for code not written: a bare string, `...`, `pass`, or a
// raise of NotImplementedError.
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

// DataNames is the names an expression reads data from: its names, a call's arguments rather than the function it
// names, and none a comprehension binds itself.
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

// boundNames is the names a comprehension binds in its own clauses.
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

// ResetsToNone says whether a method of the class other than __init__ sets the attribute back to None.
func (n Node) ResetsToNone(name string) bool {
	return slices.ContainsFunc(n.writesOutsideInit(name), func(write Node) bool { return write.Kind() == "Assign" && write.Child("value").IsNone() })
}

// writesOutsideInit is every statement of a method other than __init__ that writes the attribute on self.
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

// FieldDeclaration is where the class first declares the field: its annotation in the class body, else the
// statement of __init__ that first sets it.
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

// FillsRequiredTextWithBlank says whether the call builds a dataclass of the program and hands one of its required
// text fields an empty string: a placeholder filling a field the class says must hold text.
func (p *Program) FillsRequiredTextWithBlank(call Node) bool {
	class, ok := p.Dataclass(call.Callee().DottedName())
	if !call.IsCall() || !ok {
		return false
	}
	required := class.RequiredTextFields()

	return slices.ContainsFunc(call.fieldsHandedBlank(class.InitFieldNames()), func(field string) bool { return slices.Contains(required, field) })
}

// fieldsHandedBlank is the fields a call hands an empty string, positional arguments read in the fields' order.
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

// RequiredTextFields is the class's `str` fields that have no default.
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

// InitFieldNames is the fields a caller hands the class when building it: the annotated names of its body, less a
// ClassVar and a `field(init=False)`, which the class keeps for itself.
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

// IsContextManager says whether the def is decorated as a context manager.
func (n Node) IsContextManager() bool {
	return slices.ContainsFunc(n.Decorators(), func(decorator Node) bool {
		return slices.Contains([]string{"contextmanager", "contextlib.contextmanager", "asynccontextmanager", "contextlib.asynccontextmanager"}, decorator.DottedName())
	})
}

// HasOwnStateSaveAndRestore says whether the def saves one of its own attributes into a local and later writes it
// back: scratch state set part-way through an operation and put back after.
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
