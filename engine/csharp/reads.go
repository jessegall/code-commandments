package csharp

import (
	"slices"
	"sort"
	"strings"
)

func (n Node) IsNullTest() bool {
	return (n.Is("IsPatternExpression") && n.At(1).Is("ConstantPattern") && n.At(1).At(0).Is("NullLiteralExpression")) ||
		(n.Is("EqualsExpression") && slices.ContainsFunc(n.Expressions(), func(side Node) bool { return side.Is("NullLiteralExpression") }))
}

func (n Node) IsNotNullTest() bool {
	return (n.Is("IsPatternExpression") && n.At(1).Is("NotPattern")) ||
		(n.Is("NotEqualsExpression") && slices.ContainsFunc(n.Expressions(), func(side Node) bool { return side.Is("NullLiteralExpression") }))
}

func (n Node) IsStringKeyRead() bool {
	arguments := n.Arguments()
	if len(arguments) == 0 || !arguments[0].IsConstant() || arguments[0].Type().Name() != stringType {
		return false
	}

	return n.IsKeyedRead()
}

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

func isStringKeyed(name string) bool {
	return name != "" && (slices.Contains(jsonObjects, name) ||
		slices.ContainsFunc(dictionaries, func(dictionary string) bool { return strings.HasPrefix(name, dictionary+stringType+",") }))
}

func (n Node) KeyedReceiver() Node {
	if n.Is("ElementAccessExpression") {
		return n.At(0)
	}
	if n.IsCall() && n.At(0).Is("SimpleMemberAccessExpression") {
		return n.At(0).At(0)
	}

	return Node{}
}

func (n Node) ReadsDictionaryNamed(names []string) bool {
	receiver := n.KeyedReceiver()

	return receiver.Is("IdentifierName") && slices.Contains(names, receiver.Name())
}

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

func (n Node) OwnNames() []string {
	var names []string
	for _, node := range append(n.All(), n.Descendants()...) {
		if node.Is("Parameter", "VariableDeclarator", "SingleVariableDesignation") && node.Name() != "" {
			names = append(names, node.Name())
		}
	}

	return names
}

func (n Node) IsLoop() bool {
	return n.Is("ForStatement", "ForEachStatement", "ForEachVariableStatement", "WhileStatement", "DoStatement")
}

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

func (n Node) isFixedStepFrom(start Node) bool {
	arguments := n.Arguments()

	return n.IsCall() && start.Is("IdentifierName") && n.At(0).Is("SimpleMemberAccessExpression") &&
		n.At(0).At(0).names(start.Name()) && len(arguments) > 0 && !slices.ContainsFunc(arguments, func(argument Node) bool { return !argument.IsConstant() })
}

func (n Node) IsConstField() bool {
	return n.Is("FieldDeclaration") && n.HasModifier("const")
}

func (n Node) IsMember() bool {
	return n.Exists() && n.Node().Role == "member"
}

func (n Node) members() []Node {
	var members []Node
	for _, child := range n.All() {
		if child.IsMember() {
			members = append(members, child)
		}
	}

	return members
}

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

func (n Node) constantValues() []Node {
	var values []Node
	for _, node := range n.Descendants() {
		if node.Is("EqualsValueClause") {
			values = append(values, node.At(0))
		}
	}

	return values
}

func (n Node) isCaseLiteral() bool {
	return n.Is("NumericLiteralExpression") || (n.Is("StringLiteralExpression") && !strings.Contains(n.Text(), "\n"))
}

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

func (n Node) IsEnumCase() bool {
	return n.Is("SimpleMemberAccessExpression") && n.IsConstant() && n.Type().Exists() && n.Type().Name() == n.At(0).Type().Name()
}

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

func (n Node) isNewline() bool {
	return (n.Is("StringLiteralExpression") && (n.Text() == "\n" || n.Text() == "\r\n")) ||
		(n.Is("SimpleMemberAccessExpression") && n.At(1).Name() == "NewLine" && n.Type().Name() == stringType)
}

func (n Node) IsAppendLine() bool {
	call := n.At(0)

	return n.Is("ExpressionStatement") && call.IsCall() && call.Target().Name() == "AppendLine" && call.Target().Type() == "global::System.Text.StringBuilder"
}

func (n Node) AppendReceiver() string {
	return ExpressionHash(n.At(0).At(0).At(0))
}

func (n Node) IsAppendingFixedText() bool {
	arguments := n.At(0).Arguments()

	return len(arguments) > 0 && arguments[0].IsFixedText()
}

func (n Node) IsFixedText() bool {
	return n.Is("StringLiteralExpression", "InterpolatedStringExpression")
}

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

func unguardedLabelsOf(section Node) []Node {
	var labels []Node
	for _, label := range section.All() {
		if !label.Is("CaseSwitchLabel", "CasePatternSwitchLabel") || label.isGuarded() {
			continue
		}
		labels = append(labels, label)
	}

	return labels
}

func appendEnumCaseNames(named []string, value Node) []string {
	for _, node := range value.Flatten() {
		if !node.IsEnumCase() {
			continue
		}
		name := node.At(1).Name()
		if slices.Contains(named, name) {
			continue
		}
		named = append(named, name)
	}

	return named
}

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
			tests = append(tests, unguardedLabelsOf(section)...)
		}
	}
	var named []string
	for _, test := range tests {
		for _, value := range test.OutermostExpressions() {
			named = appendEnumCaseNames(named, value)
		}
	}

	return named
}

func (n Node) isGuarded() bool {
	return n.firstChild("WhenClause").Exists()
}

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

func (n Node) IsWrite() bool {
	return strings.HasSuffix(n.Kind(), "AssignmentExpression") || n.Is("PostIncrementExpression", "PostDecrementExpression", "PreIncrementExpression", "PreDecrementExpression")
}

func (n Node) IsRecord() bool {
	return n.Is("RecordDeclaration", "RecordStructDeclaration")
}

// State is one name a type keeps its state under, with the type it holds; no type where the compiler resolved none.
type State struct {
	Name string
	Type Type
}

func (n Node) StateNames() []string {
	var names []string
	for _, state := range n.StateTypes() {
		names = append(names, state.Name)
	}

	return names
}

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
				if !declarator.Is("VariableDeclarator") {
					continue
				}
				keep(declarator.Name(), declaration.DeclaredType())
			}
		}
	}

	return state
}
