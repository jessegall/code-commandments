package csharp

import (
	"slices"
	"sort"
	"strings"
)

// IsNullTest says whether the condition is `x is null` or `x == null`.
func (n Node) IsNullTest() bool {
	return (n.Is("IsPatternExpression") && n.At(1).Is("ConstantPattern") && n.At(1).At(0).Is("NullLiteralExpression")) ||
		(n.Is("EqualsExpression") && slices.ContainsFunc(n.Expressions(), func(side Node) bool { return side.Is("NullLiteralExpression") }))
}

// IsNotNullTest says whether the condition is `x is not null` or `x != null`.
func (n Node) IsNotNullTest() bool {
	return (n.Is("IsPatternExpression") && n.At(1).Is("NotPattern")) ||
		(n.Is("NotEqualsExpression") && slices.ContainsFunc(n.Expressions(), func(side Node) bool { return side.Is("NullLiteralExpression") }))
}

// IsStringKeyRead says whether the expression reads a dictionary or a JSON object by a string written in the
// source: `row["sku"]`, `settings.TryGetValue("timeout", out …)`, `json.GetProperty("name")`.
func (n Node) IsStringKeyRead() bool {
	arguments := n.Arguments()
	if len(arguments) == 0 || !arguments[0].IsConstant() || arguments[0].Type().Name() != stringType {
		return false
	}

	return n.IsKeyedRead()
}

// IsKeyedRead says whether the expression reads a string-keyed dictionary or a JSON object by its first
// argument, whatever that argument is.
func (n Node) IsKeyedRead() bool {
	switch {
	case n.Is("ElementAccessExpression"):
		return isStringKeyed(n.At(0).Type().Name())
	case n.IsCall() && n.Target().Name() == "GetProperty":
		return n.Target().Type() == "global::System.Text.Json.JsonElement"
	case n.IsCall() && slices.Contains([]string{"TryGetValue", "GetValueOrDefault"}, n.Target().Name()):
		return isStringKeyed(n.mapReceiverType())
	}

	return false
}

// isStringKeyed says whether the type is a dictionary keyed by strings, or a JSON object.
func isStringKeyed(name string) bool {
	return name != "" && (slices.Contains(jsonObjects, name) ||
		slices.ContainsFunc(dictionaries, func(dictionary string) bool { return strings.HasPrefix(name, dictionary+stringType+",") }))
}

// KeyedReceiver is what a keyed read reads from: the indexed expression, or the receiver of the lookup method;
// no node for a call on nothing named.
func (n Node) KeyedReceiver() Node {
	if n.Is("ElementAccessExpression") {
		return n.At(0)
	}
	if n.IsCall() && n.At(0).Is("SimpleMemberAccessExpression") {
		return n.At(0).At(0)
	}

	return Node{}
}

// ReadsDictionaryNamed says whether the keyed read reads a dictionary held under one of the names: a variable
// named there, not a member reached through another object.
func (n Node) ReadsDictionaryNamed(names []string) bool {
	receiver := n.KeyedReceiver()

	return receiver.Is("IdentifierName") && slices.Contains(names, receiver.Name())
}

// ValueParamSignature is the member's value parameters, each `type name`, sorted, when it takes three or more;
// none otherwise. Two members of different types with the same signature thread one clump of data.
func (n Node) ValueParamSignature() []string {
	var fields []string
	for _, parameter := range n.firstChild("ParameterList").All() {
		written := parameter.Type()
		if !written.Exists() {
			continue
		}
		if name := strings.TrimSuffix(written.Name(), "?"); slices.Contains(scalars, name) {
			fields = append(fields, name+" "+parameter.Name())
		}
	}
	sort.Strings(fields)
	if len(fields) < 3 {
		return nil
	}

	return fields
}

// OwnNames is the names the function declares for its own use: its parameters, and every local its body declares.
func (n Node) OwnNames() []string {
	var names []string
	for _, node := range append(n.All(), n.Descendants()...) {
		if node.Is("Parameter", "VariableDeclarator", "SingleVariableDesignation") && node.Name() != "" {
			names = append(names, node.Name())
		}
	}

	return names
}

// IsLoop says whether the statement is a `for`, `foreach`, `while` or `do`.
func (n Node) IsLoop() bool {
	return n.Is("ForStatement", "ForEachStatement", "ForEachVariableStatement", "WhileStatement", "DoStatement")
}

// IsNonCountingFor says whether the `for`'s step moves no counter: it assigns the next item instead, as in
// `for (var link = head; link != null; link = link.Next)`. A `for` with no step at all is not one.
func (n Node) IsNonCountingFor() bool {
	if !n.Is("ForStatement") {
		return false
	}
	var steps []Node
	for _, child := range n.All() {
		if child.IsStep() {
			steps = append(steps, child)
		}
	}

	return len(steps) > 0 && !slices.ContainsFunc(steps, Node.AdvancesACounter)
}

// AdvancesACounter says whether the expression moves a counter along: `i++`, `++i`, `i--`, `--i`, `i += n`,
// `i -= n`, an assignment of one of those, or a step by a fixed amount (`date = date.PlusDays(1)`).
func (n Node) AdvancesACounter() bool {
	if n.Is("PostIncrementExpression", "PreIncrementExpression", "PostDecrementExpression", "PreDecrementExpression", "AddAssignmentExpression", "SubtractAssignmentExpression") {
		return true
	}
	if !n.Is("SimpleAssignmentExpression") {
		return false
	}
	target, value := n.At(0), n.At(1)

	return value.AdvancesACounter() || value.isFixedStepFrom(target)
}

// isFixedStepFrom says whether the expression is a call on the start handed only constants: `date.PlusDays(1)`.
func (n Node) isFixedStepFrom(start Node) bool {
	arguments := n.Arguments()

	return n.IsCall() && start.Is("IdentifierName") && n.At(0).Is("SimpleMemberAccessExpression") &&
		n.At(0).At(0).names(start.Name()) && len(arguments) > 0 && !slices.ContainsFunc(arguments, func(argument Node) bool { return !argument.IsConstant() })
}

// IsConstField says whether the node is a `const` field: a value fixed when the program is compiled.
func (n Node) IsConstField() bool {
	return n.Is("FieldDeclaration") && n.HasModifier("const")
}

// IsMember says whether the node declares a type or a member of one.
func (n Node) IsMember() bool {
	return n.Exists() && n.Node().Role == "member"
}

// members is the node's children that declare a member.
func (n Node) members() []Node {
	var members []Node
	for _, child := range n.All() {
		if child.IsMember() {
			members = append(members, child)
		}
	}

	return members
}

// IsConstClassEnum says whether the class holds two or more `const` fields, each a one-line string or number
// written in the source, and nothing else: a closed set spelled out as constants.
func (n Node) IsConstClassEnum() bool {
	members := n.members()
	if !n.Is("ClassDeclaration") || len(members) == 0 {
		return false
	}
	var values []Node
	for _, member := range members {
		if !member.IsConstField() {
			return false
		}
		values = append(values, member.constantValues()...)
	}

	return len(values) >= 2 && !slices.ContainsFunc(values, func(value Node) bool { return !value.isCaseLiteral() })
}

// constantValues is the values the field declaration's declarators are given.
func (n Node) constantValues() []Node {
	var values []Node
	for _, node := range n.Descendants() {
		if node.Is("EqualsValueClause") {
			values = append(values, node.At(0))
		}
	}

	return values
}

// isCaseLiteral says whether the literal could name a case: a number, or a string on one line.
func (n Node) isCaseLiteral() bool {
	return n.Is("NumericLiteralExpression") || (n.Is("StringLiteralExpression") && !strings.Contains(n.Text(), "\n"))
}

// EnumsTestedAsAGroup is the enums the `||` chain or `or` pattern tests two or more different cases of:
// `Status` in `s == Status.Paid || s == Status.Refunded` and in `s is Status.Paid or Status.Refunded`.
func (n Node) EnumsTestedAsAGroup() []string {
	var order []string
	casesByEnum := map[string]map[string]bool{}
	for _, operand := range n.orOperands() {
		for _, tested := range operand.testedEnumCases() {
			enum := tested.Type().Name()
			if casesByEnum[enum] == nil {
				casesByEnum[enum] = map[string]bool{}
				order = append(order, enum)
			}
			casesByEnum[enum][tested.At(1).Name()] = true
		}
	}
	var grouped []string
	for _, enum := range order {
		if len(casesByEnum[enum]) >= 2 {
			grouped = append(grouped, enum)
		}
	}

	return grouped
}

// orOperands is the sides of the `||` chain or `or` pattern, the nested ones of the same kind unrolled.
func (n Node) orOperands() []Node {
	var operands []Node
	for _, side := range n.All() {
		if side.Kind() == n.Kind() {
			operands = append(operands, side.orOperands()...)
			continue
		}
		operands = append(operands, side)
	}

	return operands
}

// testedEnumCases is the enum case the operand tests for: the member in `s == Status.Paid` or in the pattern
// `Status.Paid`.
func (n Node) testedEnumCases() []Node {
	if !n.Is("EqualsExpression", "ConstantPattern") {
		return nil
	}
	var cases []Node
	for _, side := range n.All() {
		if side.IsEnumCase() {
			cases = append(cases, side)
		}
	}

	return cases
}

// IsEnumCase says whether the expression is an enum case named through its enum: `Status.Paid`, a constant of
// the very type it is read from.
func (n Node) IsEnumCase() bool {
	return n.Is("SimpleMemberAccessExpression") && n.IsConstant() && n.Type().Exists() && n.Type().Name() == n.At(0).Type().Name()
}

// MembershipLiterals is the strings the expression tests a value's membership in, when it tests it against
// nothing but strings written right there: `"paid"` and `"late"` in `new[] { "paid", "late" }.Contains(status)`
// and in `status is "paid" or "late"`; none for anything else.
func (n Node) MembershipLiterals() []string {
	var elements []Node
	switch {
	case n.Is("IsPatternExpression") && n.At(1).Is("OrPattern"):
		for _, operand := range n.At(1).orOperands() {
			if operand.Is("ConstantPattern") {
				operand = operand.At(0)
			}
			elements = append(elements, operand)
		}
	case n.IsCall() && n.Target().Name() == "Contains" && n.At(0).Is("SimpleMemberAccessExpression"):
		elements = n.At(0).At(0).WithoutParentheses().writtenElements()
	}

	return literalTexts(elements)
}

// literalTexts is the texts of the elements when every one is a string literal; none otherwise.
func literalTexts(elements []Node) []string {
	texts := []string{}
	for _, element := range elements {
		if !element.Is("StringLiteralExpression") {
			return []string{}
		}
		texts = append(texts, element.Text())
	}

	return texts
}

// JoinedLines is the lines a `string.Join` puts on separate lines: its elements, written right there as an array,
// a collection expression or its `params`, when the separator is a newline; none for any other call.
func (n Node) JoinedLines() []Node {
	arguments := n.Arguments()
	if !n.IsCall() || n.Target().Type() != stringType || n.Target().Name() != "Join" || len(arguments) == 0 || !arguments[0].isNewline() {
		return nil
	}
	if len(arguments) == 2 {
		return arguments[1].WithoutParentheses().writtenElements()
	}

	return arguments[1:]
}

// isNewline says whether the value is a line break: `"\n"`, `"\r\n"`, or `Environment.NewLine`.
func (n Node) isNewline() bool {
	return (n.Is("StringLiteralExpression") && (n.Text() == "\n" || n.Text() == "\r\n")) ||
		(n.Is("SimpleMemberAccessExpression") && n.At(1).Name() == "NewLine" && n.Type().Name() == stringType)
}

// IsAppendLine says whether the statement is `builder.AppendLine(…)` on a `StringBuilder`: one line of a text
// written line by line.
func (n Node) IsAppendLine() bool {
	call := n.At(0)

	return n.Is("ExpressionStatement") && call.IsCall() && call.Target().Name() == "AppendLine" && call.Target().Type() == "global::System.Text.StringBuilder"
}

// AppendReceiver is the builder an `AppendLine` statement writes to, as a fingerprint, so a run on one builder is
// told from a run that switches to another.
func (n Node) AppendReceiver() string {
	return ExpressionHash(n.At(0).At(0).At(0))
}

// IsAppendingFixedText says whether the line an `AppendLine` statement writes is text written into the source.
func (n Node) IsAppendingFixedText() bool {
	arguments := n.At(0).Arguments()

	return len(arguments) > 0 && arguments[0].IsFixedText()
}

// IsFixedText says whether the value is text written into the source, a string literal or an interpolated string,
// rather than a value worked out.
func (n Node) IsFixedText() bool {
	return n.Is("StringLiteralExpression", "InterpolatedStringExpression")
}

// writtenElements is the elements of the collection the expression writes out: an array, a collection
// initializer or a collection expression, through a cast; none for anything else.
func (n Node) writtenElements() []Node {
	switch {
	case n.Is("CollectionExpression"):
		var elements []Node
		for _, element := range n.All() {
			elements = append(elements, element.At(0))
		}

		return elements
	case n.Is("CastExpression"):
		all := n.All()

		return all[len(all)-1].WithoutParentheses().writtenElements()
	case n.Is("ArrayCreationExpression", "ImplicitArrayCreationExpression", "ObjectCreationExpression"):
		for _, child := range n.All() {
			if strings.HasSuffix(child.Kind(), "InitializerExpression") {
				return child.All()
			}
		}
	}

	return nil
}

// NamedCases is the enum members the switch names in its cases, arms and labels guarded by `when` left out,
// since they do not match every time.
func (n Node) NamedCases() []string {
	var tests []Node
	switch {
	case n.Is("SwitchExpression"):
		for _, arm := range n.All()[1:] {
			if !arm.isGuarded() {
				tests = append(tests, arm.At(0))
			}
		}
	case n.Is("SwitchStatement"):
		for _, section := range n.All()[1:] {
			for _, label := range section.All() {
				if label.Is("CaseSwitchLabel", "CasePatternSwitchLabel") && !label.isGuarded() {
					tests = append(tests, label)
				}
			}
		}
	}
	var named []string
	for _, test := range tests {
		for _, value := range test.OutermostExpressions() {
			for _, node := range value.Flatten() {
				if node.IsEnumCase() && !slices.Contains(named, node.At(1).Name()) {
					named = append(named, node.At(1).Name())
				}
			}
		}
	}

	return named
}

// isGuarded says whether the case test only matches when its `when` clause holds.
func (n Node) isGuarded() bool {
	return n.firstChild("WhenClause").Exists()
}

// FallbackValue is what the switch hands back when no case matches: the value of its `_` arm, or what the
// `default:` section returns; no node when it has neither.
func (n Node) FallbackValue() Node {
	var fallback Node
	switch {
	case n.Is("SwitchExpression"):
		for _, arm := range n.All()[1:] {
			if arm.At(0).Is("DiscardPattern") {
				fallback = arm
				break
			}
		}
	case n.Is("SwitchStatement"):
		for _, section := range n.All()[1:] {
			if section.firstChild("DefaultSwitchLabel").Exists() {
				fallback = section
				break
			}
		}
	}
	for _, child := range fallback.All() {
		if child.Is("ReturnStatement") {
			return child.At(0)
		}
		if child.IsExpression() {
			return child
		}
	}

	return Node{}
}

// LiteralKeys is the keys the dictionary is built with, when every one is a string written in the source: `sku`
// and `qty` in `new Dictionary<string, object> { ["sku"] = …, ["qty"] = … }`; none for anything else.
func (n Node) LiteralKeys() []string {
	if !n.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression") || !isDictionary(n.Type().Name()) {
		return []string{}
	}
	var entries, keys []Node
	for _, initializer := range n.All() {
		if initializer.Is("ObjectInitializerExpression", "CollectionInitializerExpression") {
			entries = append(entries, initializer.All()...)
		}
	}
	for _, entry := range entries {
		keys = append(keys, entry.entryKey()...)
	}
	if len(entries) == 0 || len(keys) != len(entries) {
		return []string{}
	}

	return literalTexts(keys)
}

// entryKey is the key of a dictionary initializer entry: `"sku"` in `["sku"] = …` and in `{ "sku", … }`.
func (n Node) entryKey() []Node {
	switch {
	case n.Is("SimpleAssignmentExpression") && n.At(0).Is("ImplicitElementAccess"):
		if arguments := n.At(0).Arguments(); len(arguments) > 0 {
			return arguments[:1]
		}
	case n.Is("ComplexElementInitializerExpression"):
		if all := n.All(); len(all) > 0 {
			return all[:1]
		}
	}

	return nil
}

// IsWrite says whether the expression writes the target it names first: an assignment of any kind, or a step up
// or down.
func (n Node) IsWrite() bool {
	return strings.HasSuffix(n.Kind(), "AssignmentExpression") || n.Is("PostIncrementExpression", "PostDecrementExpression", "PreIncrementExpression", "PreDecrementExpression")
}

// IsRecord says whether the type is a record: a class or a struct declared as a value.
func (n Node) IsRecord() bool {
	return n.Is("RecordDeclaration", "RecordStructDeclaration")
}

// State is one name a type keeps its state under, with the type it holds; no type where the compiler resolved none.
type State struct {
	Name string
	Type Type
}

// StateNames is the names the type keeps its state under: its primary constructor's parameters, its properties
// and its instance fields.
func (n Node) StateNames() []string {
	var names []string
	for _, state := range n.StateTypes() {
		names = append(names, state.Name)
	}

	return names
}

// StateTypes is the state the type keeps, each name with the type it holds, in the order the type declares it.
func (n Node) StateTypes() []State {
	var state []State
	keep := func(name string, held Type) {
		if name == "" {
			return
		}
		for at := range state {
			if state[at].Name == name {
				state[at].Type = held
				return
			}
		}
		state = append(state, State{Name: name, Type: held})
	}
	for _, list := range n.All() {
		if list.Is("ParameterList") {
			for _, parameter := range list.All() {
				keep(parameter.Name(), parameter.Type())
			}
		}
	}
	for _, property := range n.All() {
		if property.Is("PropertyDeclaration") {
			keep(property.Name(), property.DeclaredType())
		}
	}
	for _, field := range n.All() {
		if !field.Is("FieldDeclaration") || field.HasModifier("static") || field.HasModifier("const") {
			continue
		}
		for _, declaration := range field.Descendants() {
			if !declaration.Is("VariableDeclaration") {
				continue
			}
			for _, declarator := range declaration.All() {
				if declarator.Is("VariableDeclarator") {
					keep(declarator.Name(), declaration.DeclaredType())
				}
			}
		}
	}

	return state
}
