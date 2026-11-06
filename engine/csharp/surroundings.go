package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) Ancestors() []Node {
	var ancestors []Node
	for around := n.Parent(); around.Exists(); around = around.Parent() {
		ancestors = append(ancestors, around)
	}

	return ancestors
}

func (n Node) EnclosingFunction() Node {
	for _, around := range n.Ancestors() {
		if around.IsFunction() {
			return around
		}
	}

	return Node{}
}

func (n Node) EnclosingType() Node {
	for _, around := range n.Ancestors() {
		if around.Is("ClassDeclaration", "RecordDeclaration", "StructDeclaration", "RecordStructDeclaration", "InterfaceDeclaration") {
			return around
		}
	}

	return Node{}
}

func (n Node) DeclaredName() string {
	if n.Name() != "" {
		return n.Name()
	}

	if only := n.OnlyDeclarator(); only.Exists() {
		return only.Name()
	}

	if !strings.HasSuffix(n.Kind(), "AccessorDeclaration") {
		return ""
	}

	for _, member := range n.Ancestors() {
		if member.Is("PropertyDeclaration", "IndexerDeclaration", "EventDeclaration") && member.Name() != "" {
			return member.Name()
		}
	}

	return ""
}

func (n Node) Declarators() []Node {
	if !n.Is("FieldDeclaration", "EventFieldDeclaration") {
		return nil
	}

	var declared []Node
	for _, declarator := range n.firstChild("VariableDeclaration").All() {
		if declarator.Is("VariableDeclarator") {
			declared = append(declared, declarator)
		}
	}

	return declared
}

func (n Node) OnlyDeclarator() Node {
	declared := n.Declarators()
	if len(declared) != 1 {
		return Node{}
	}

	return declared[0]
}

func (n Node) DeclaresVariableNamed(name string) bool {
	return slices.ContainsFunc(n.Declarators(), func(declarator Node) bool { return declarator.Name() == name })
}

func (n Node) IsStub() bool {
	return n.FunctionBody().thrown().Type().Name() == "global::System.NotImplementedException"
}

func (n Node) thrown() Node {
	var throw Node
	switch {
	case n.Is("Block") && len(n.Children()) == 1 && n.Children()[0].Is("ThrowStatement"):
		throw = n.Children()[0]
	case n.Is("ArrowExpressionClause") && n.At(0).Is("ThrowExpression"):
		throw = n.At(0)
	}
	if expressions := throw.Expressions(); len(expressions) > 0 {
		return expressions[0]
	}

	return Node{}
}

func (n Node) IsElseIf() bool {
	return n.Is("IfStatement") && n.Parent().Is("ElseClause")
}

func (n Node) FillsArgument() bool {
	return n.Parent().Is("Argument")
}

func (n Node) AddsNothing() bool {
	if !n.Fallback().Is("NumericLiteralExpression") || strings.Trim(n.Fallback().Text(), "0.") != "" {
		return false
	}

	added := n.OutermostParentheses().Parent()
	if added.Is("AddExpression", "AddAssignmentExpression") {
		return true
	}

	return added.Is("Argument") && strings.Contains(callOf(added), "Increment")
}

func (n Node) AnswerIsAccumulated() bool {
	answer := n.OutermostParentheses()
	taken := answer.Parent()

	if taken.Is("AddExpression", "AddAssignmentExpression") {
		return sums(taken)
	}

	return taken.Is("Argument") && strings.Contains(callOf(taken), "Increment")
}

func sums(addition Node) bool {
	return slices.Contains(numbers, strings.TrimPrefix(addition.Type().Name(), "global::System."))
}

var numbers = []string{
	"int", "long", "short", "byte", "sbyte", "uint", "ulong", "ushort", "float", "double", "decimal",
	"Int32", "Int64", "Int16", "Byte", "SByte", "UInt32", "UInt64", "UInt16", "Single", "Double", "Decimal",
}

func callOf(argument Node) string {
	call := argument.Parent().Parent()
	if !call.IsCall() {
		return ""
	}

	return call.CalleeName()
}

func (n Node) OutermostParentheses() Node {
	inner := n
	for inner.Parent().Is("ParenthesizedExpression") {
		inner = inner.Parent()
	}

	return inner
}

func (n Node) IsComparedToItsFallback() bool {
	fallback := n.Fallback()
	compared := n.OutermostParentheses()
	comparison := compared.Parent()
	if !fallback.Exists() || !comparison.Is("EqualsExpression", "NotEqualsExpression") {
		return false
	}

	return slices.ContainsFunc(comparison.All(), func(side Node) bool {
		return side.Node() != compared.Node() && side.IsSameValueAs(fallback)
	})
}

func (n Node) IsWrappingWithoutCause() bool {
	if !n.Is("ThrowStatement", "ThrowExpression") {
		return false
	}
	created := Node{}
	for _, thrown := range n.Expressions() {
		if thrown.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression") {
			created = thrown
			break
		}
	}
	if !created.Exists() {
		return false
	}
	for _, ancestor := range n.Ancestors() {
		if ancestor.IsFunction() {
			return false
		}
		if ancestor.Is("CatchClause") {
			caught := ancestor.firstChild("CatchDeclaration").Name()

			return caught == "" || !handsOver(created, caught)
		}
	}

	return false
}

func (n Node) FillsAnOutsideContract() bool {
	for _, ancestor := range n.Ancestors() {
		if ancestor.IsFunction() {
			return ancestor.FillsOutsideTheSolution()
		}
	}

	return false
}

func handsOver(created Node, caught string) bool {
	for _, part := range append(created.Flatten(), created) {
		if !part.IsCall() && !part.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression") {
			continue
		}

		for _, argument := range part.Arguments() {
			if argument.WithoutParentheses().Is("IdentifierName") && argument.WithoutParentheses().Name() == caught {
				return true
			}
		}
	}

	return false
}

func (n Node) IsConstructorSideEffect() bool {
	if !n.IsCall() || !n.At(0).Is("SimpleMemberAccessExpression") || !n.Parent().Is("ExpressionStatement") {
		return false
	}
	scope := Node{}
	for _, ancestor := range n.Ancestors() {
		if ancestor.Is("TryStatement") && ancestor.firstChild("CatchClause").Exists() {
			return false
		}
		if ancestor.IsFunction() {
			scope = ancestor
			break
		}
	}

	return scope.Is("ConstructorDeclaration") && slices.Contains(scope.collaborators(), heldName(n.At(0).At(0)))
}

func (n Node) collaborators() []string {
	var names []string
	for _, parameter := range n.parameters() {
		if parameter.Is("Parameter") && parameter.Name() != "" {
			names = append(names, parameter.Name())
		}
	}
	parameters := slices.Clone(names)
	for _, assignment := range n.expressionParts() {
		if assignment.Is("SimpleAssignmentExpression") && assignment.At(1).Is("IdentifierName") && slices.Contains(parameters, assignment.At(1).Name()) {
			if held := heldName(assignment.At(0)); held != "" {
				names = append(names, held)
			}
		}
	}

	return names
}

func heldName(expression Node) string {
	switch {
	case expression.Is("IdentifierName"):
		return expression.Name()
	case expression.Is("SimpleMemberAccessExpression") && expression.At(0).Is("ThisExpression"):
		return expression.At(1).Name()
	}

	return ""
}

func (n Node) IsWritingStaticState() bool {
	scope := n.EnclosingFunction()
	if !n.IsWrite() || !scope.Exists() || (scope.Is("ConstructorDeclaration") && scope.HasModifier("static")) {
		return false
	}
	owner := n.EnclosingType()

	return owner.Exists() && slices.Contains(owner.mutableStaticFields(), fieldWritten(n.At(0), owner, scope))
}

func fieldWritten(target, owner, scope Node) string {
	if target.Is("SimpleMemberAccessExpression") {
		if target.At(0).Is("IdentifierName") && target.At(0).Name() == owner.Name() {
			return target.At(1).Name()
		}

		return ""
	}
	if !target.Is("IdentifierName") || slices.Contains(scope.OwnNames(), target.Name()) {
		return ""
	}

	return target.Name()
}

func (n Node) mutableStaticFields() []string {
	var names []string
	for _, field := range n.All() {
		if !field.Is("FieldDeclaration") || !field.HasModifier("static") || field.HasModifier("readonly") || field.HasModifier("const") {
			continue
		}
		for _, declarator := range field.Descendants() {
			if declarator.Is("VariableDeclarator") && declarator.Name() != "" {
				names = append(names, declarator.Name())
			}
		}
	}

	return names
}

func (n Node) IsBuriedThrow() bool {
	if !n.Is("CoalesceExpression") || !n.At(1).Is("ThrowExpression") {
		return false
	}
	inner := n.OutermostParentheses()
	around := inner.Parent()

	return around.Is("Argument") || (around.Is("SimpleMemberAccessExpression", "ConditionalAccessExpression") && around.At(0).Node() == inner.Node())
}

func (n Node) IsGroupTestRoot() bool {
	switch {
	case n.Is("LogicalOrExpression"):
		return !n.Parent().Is("LogicalOrExpression")
	case n.Is("OrPattern"):
		return n.Parent().Is("IsPatternExpression")
	}

	return false
}

func (n Node) IsWritingRecordState() bool {
	scope := n.EnclosingFunction()
	initializing := strings.HasSuffix(n.Parent().Kind(), "InitializerExpression")
	if !n.IsWrite() || n.Is("CoalesceAssignmentExpression") || initializing || !scope.Exists() || scope.Is("ConstructorDeclaration", "InitAccessorDeclaration") {
		return false
	}
	owner := n.EnclosingType()
	if !owner.IsRecord() {
		return false
	}
	own := scope.OwnNames()
	var state []string
	for _, name := range owner.StateNames() {
		if !slices.Contains(own, name) {
			state = append(state, name)
		}
	}

	return n.At(0).ReadsMember(state)
}

func (n Node) IsRecordSetter() bool {
	return n.Is("SetAccessorDeclaration") && n.EnclosingType().IsRecord()
}

func (n Node) IsNullObjectOfItsType() bool {
	member := Node{}
	for _, around := range n.Ancestors() {
		if around.IsMember() {
			member = around
			break
		}
	}
	owner := n.EnclosingType()

	return member.Is("FieldDeclaration", "PropertyDeclaration") && member.HasModifier("static") && owner.Exists() && owner.Symbol() == n.Type().Name()
}

func (n Node) IsOutermostAnd() bool {
	return n.Is("LogicalAndExpression") && !n.OutermostParentheses().Parent().Is("LogicalAndExpression")
}

func (n Node) IsStoredValue() bool {
	return n.OutermostParentheses().Parent().Is("EqualsValueClause", "SimpleAssignmentExpression")
}

func (n Node) StartsAppendLineRun(lines, fixed int) bool {
	block := n.Parent()
	if !n.IsAppendLine() || !block.Exists() {
		return false
	}
	siblings := block.All()
	at := slices.IndexFunc(siblings, func(sibling Node) bool { return sibling.Node() == n.Node() })
	receiver := n.AppendReceiver()
	onSame := func(position int) bool {
		return position >= 0 && position < len(siblings) && siblings[position].IsAppendLine() && siblings[position].AppendReceiver() == receiver
	}
	if onSame(at - 1) {
		return false
	}
	run, written := 0, 0
	for position := at; onSame(position); position++ {
		run++
		if siblings[position].IsAppendingFixedText() {
			written++
		}
	}

	return run >= lines && written >= fixed
}

func (n Node) IsReturned() bool {
	return n.OutermostParentheses().Parent().Is("ReturnStatement", "ArrowExpressionClause")
}

func (n Node) IsConditionalBranch() bool {
	inner := n.OutermostParentheses()
	around := inner.Parent()

	return around.Is("ConditionalExpression") && around.At(0).Node() != inner.Node()
}

func (n Node) IsInventingOnMiss() bool {
	body := n.FunctionBody()
	if !body.Exists() {
		return false
	}
	scope := append([]Node{body}, body.Descendants()...)
	var answers []Node
	for _, node := range scope {
		if value := node.ReturnedValue(); node.IsReturn() && value.Exists() {
			answers = append(answers, value.Answers()...)
		}
	}
	found := lookedUpNames(scope)
	invented, real := 0, 0
	for _, answer := range answers {
		if answer.IsEmptyScalar() {
			invented++
			continue
		}
		real++
		if !answer.IsLookup() && !(answer.Is("IdentifierName") && slices.Contains(found, answer.Name())) {
			return false
		}
	}

	return invented > 0 && real > 0
}

func declaredNamesIn(call Node) []string {
	var names []string
	for _, part := range call.Flatten() {
		if !part.Is("DeclarationExpression") {
			continue
		}
		for _, designation := range part.All() {
			if !designation.Is("SingleVariableDesignation") || designation.Name() == "" {
				continue
			}
			names = append(names, designation.Name())
		}
	}

	return names
}

func lookedUpNames(scope []Node) []string {
	var names []string
	for _, node := range scope {
		for _, expression := range node.Expressions() {
			for _, lookup := range expression.Flatten() {
				if !lookup.IsLookup() || !lookup.IsCall() {
					continue
				}
				names = append(names, declaredNamesIn(lookup)...)
			}
		}
	}

	return names
}

func (n Node) IsWithinNamedConstructor() bool {
	for _, member := range n.Ancestors() {
		if member.Is("ConstructorDeclaration", "MethodDeclaration") {
			return n.isNamedConstructorOf(member)
		}
	}

	return false
}

func (n Node) IsNamedConstructor() bool {
	return n.isNamedConstructorOf(n)
}

func (n Node) isNamedConstructorOf(member Node) bool {
	owner := n.EnclosingType()

	return member.Is("ConstructorDeclaration") ||
		(member.HasModifier("static") && owner.Exists() && member.writtenTypeNode().Exists() && member.writtenTypeNode().Name() == owner.Name())
}

func (n Node) DefaultedNameTestedForBlankness() bool {
	owner := n.EnclosingType()
	if n.Is("Parameter") {
		owner = n.EnclosingFunction()
	}

	return owner.Exists() && slices.ContainsFunc(owner.expressionParts(), func(expression Node) bool { return expression.TestsBlanknessOf(n.Name()) })
}

func (n Node) Owner() string {
	if owner := n.EnclosingType(); owner.Exists() {
		return n.File() + "::" + owner.Name()
	}

	return n.File()
}

func (n Node) IsWithinTryMethod() bool {
	for _, method := range n.Ancestors() {
		if method.Is("MethodDeclaration", "LocalFunctionStatement") {
			return slices.ContainsFunc(method.parameters(), func(parameter Node) bool { return parameter.HasModifier("out") })
		}
	}

	return false
}

func (n Node) before() []Node {
	siblings := n.Parent().All()
	at := slices.IndexFunc(siblings, func(sibling Node) bool { return sibling.Node() == n.Node() })
	if at < 0 {
		return nil
	}

	return siblings[:at]
}

func (n Node) IsMemberAfterMethod() bool {
	if !n.IsStateMember() || !n.Parent().Exists() || n.Parent().Is("InterfaceDeclaration") {
		return false
	}

	return slices.ContainsFunc(n.before(), func(member Node) bool { return member.Is("MethodDeclaration", "ConstructorDeclaration") })
}

func (n Node) IsMemberOutOfOrder() bool {
	return n.IsConstantMember() && slices.ContainsFunc(n.before(), Node.IsInstanceStateMember)
}

func (n Node) IsReadingOwnDictionary() bool {
	var names []string
	for _, function := range n.Ancestors() {
		if function.IsFunction() {
			names = append(names, function.OwnNames()...)
		}
	}

	return n.ReadsDictionaryNamed(names)
}

func (n Node) IsAssignedTo() bool {
	around := n.Parent()

	return strings.HasSuffix(around.Kind(), "AssignmentExpression") && around.At(0).Node() == n.Node()
}

func (n Node) IsWithinOverride() bool {
	return slices.ContainsFunc(n.Ancestors(), Node.IsInherited)
}

func (n Node) IsBuildingAnObject() bool {
	for _, ancestor := range n.Ancestors() {
		if ancestor.IsFunction() {
			return false
		}
		if ancestor.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression") {
			return true
		}
	}

	return false
}

func (n Node) ConvertsToADeclaredType(program *Program) bool {
	for _, ancestor := range n.Ancestors() {
		if !ancestor.IsFunction() {
			continue
		}

		answered := strings.TrimSuffix(ancestor.ReturnType().Name(), "?")
		if answered == "" {
			return false
		}

		return slices.ContainsFunc(ancestor.OutermostExpressions(), func(expression Node) bool {
			return slices.ContainsFunc(append(expression.Flatten(), expression), func(part Node) bool {
				return builds(part, answered) && program.DeclaresType(part.Type().Name())
			})
		})
	}

	return false
}

func builds(expression Node, named string) bool {
	if expression.Is("ImplicitObjectCreationExpression") {
		return true
	}

	return expression.Is("ObjectCreationExpression") && strings.HasSuffix(expression.Type().Name(), "."+named)
}

func (n Node) ComparedLiterals() []string {
	var values []Node
	switch {
	case n.Is("SwitchStatement", "SwitchExpression"):
		for _, node := range n.Descendants() {
			if node.Is("CaseSwitchLabel", "ConstantPattern") {
				values = append(values, node.Expressions()...)
			}
		}
	case n.Is("IfStatement") && !n.IsElseIf():
		for _, rung := range engine.LadderRungs(n.Match) {
			values = append(values, Node{Match: rung}.At(0))
		}
	}
	var literals []string
	for _, value := range values {
		for _, literal := range value.Flatten() {
			if literal.Is("StringLiteralExpression") {
				literals = append(literals, literal.Text())
			}
		}
	}

	return literals
}

func (n Node) BranchingDepth() int {
	depth, child := 0, n
	for _, parent := range n.Ancestors() {
		if parent.IsFunction() {
			break
		}
		if parent.IsBranchingConstruct() && !(child.Is("ElseClause") && len(child.Children()) > 0 && child.Children()[0].Is("IfStatement")) {
			depth++
		}
		child = parent
	}

	return depth
}

func (n Node) IsInTest() bool {
	return n.Exists() && n.Source().Test
}

var collectionMutators = []string{"Add", "AddRange", "Insert", "InsertRange", "Remove", "RemoveAt", "RemoveAll", "RemoveRange", "Clear", "Sort", "Reverse", "Enqueue", "Dequeue", "Push", "Pop", "TryAdd", "TryRemove"}

func (n Node) ChangesTheCollectionItsLoopWalks() bool {
	changed := n.At(0).At(0)
	if !n.IsCall() || !n.At(0).Is("SimpleMemberAccessExpression") || !slices.Contains(collectionMutators, n.At(0).ReferencedName()) {
		return false
	}
	loop, walked := n.loopWalking(changed)
	if !walked {
		return false
	}

	return !n.leavesThatLoopAtOnce(loop)
}

func (n Node) loopWalking(collection Node) (Node, bool) {
	for held, loop := n, n.Parent(); loop.Exists(); held, loop = loop, loop.Parent() {
		if !loop.Is("ForEachStatement", "ForEachVariableStatement") || held.IsExpression() {
			continue
		}
		if walked, walks := loop.Expressions()[0].collectionWalkedLive(); walks && walked.SameSyntax(collection.Match) {
			return loop, true
		}
	}

	return Node{}, false
}

func (n Node) collectionWalkedLive() (Node, bool) {
	if n.Is("SimpleMemberAccessExpression") && slices.Contains([]string{"Keys", "Values"}, n.ReferencedName()) {
		return n.At(0).collectionWalkedLive()
	}
	if strings.Contains(n.Type().Name(), "System.Collections.Concurrent.") {
		return Node{}, false
	}

	return n, n.Is("IdentifierName", "SimpleMemberAccessExpression")
}

func (n Node) leavesThatLoopAtOnce(loop Node) bool {
	block := n.statementsHolding()
	if len(block) == 0 {
		return false
	}

	return block[len(block)-1].leaves(loop)
}

func (n Node) statementsHolding() []Node {
	for held := n; held.Exists(); held = held.Parent() {
		if held.Parent().Is("Block") {
			return held.Parent().Children()
		}
	}

	return nil
}

func (n Node) leaves(loop Node) bool {
	if n.Is("ReturnStatement", "ThrowStatement", "YieldBreakStatement") {
		return true
	}

	return n.Is("BreakStatement") && n.nearestLoop().Node() == loop.Node()
}

func (n Node) nearestLoop() Node {
	for loop := n.Parent(); loop.Exists(); loop = loop.Parent() {
		if loop.IsLoop() {
			return loop
		}
	}

	return Node{}
}

func (n Node) IsUnreadCollaborator() bool {
	name, taken := n.collaboratorTaken()
	owner := n.EnclosingType()
	if !taken || owner.HasModifier("partial") || owner.holdsATypeOfItsOwn() {
		return false
	}

	return !owner.namesTheCollaborator(name, n)
}

func (n Node) collaboratorTaken() (string, bool) {
	switch {
	case n.Is("VariableDeclarator") && n.Parent().Parent().Is("FieldDeclaration"):
		field := n.Parent().Parent()
		if !field.HasModifier("private") || !field.HasModifier("readonly") || field.hasAttribute() {
			return "", false
		}
		if field.HasModifier("static") {
			return "", false
		}

		return n.Name(), true
	case n.Is("Parameter") && n.Parent().Is("ParameterList") && n.Parent().Parent().Is("ClassDeclaration", "StructDeclaration"):
		if n.hasAttribute() {
			return "", false
		}

		return n.Name(), true
	}

	return "", false
}

func (n Node) hasAttribute() bool {
	return slices.ContainsFunc(n.All(), func(child Node) bool { return child.Is("AttributeList") })
}

func (n Node) holdsATypeOfItsOwn() bool {
	return slices.ContainsFunc(n.Children(), func(member Node) bool {
		return member.Is("ClassDeclaration", "RecordDeclaration", "StructDeclaration", "InterfaceDeclaration", "EnumDeclaration")
	})
}

func (n Node) namesTheCollaborator(name string, declaration Node) bool {
	return slices.ContainsFunc(n.Match.Descendants(), func(match engine.Match) bool {
		node := Node{Match: match}
		if node.Node() == declaration.Node() || !node.Is("IdentifierName") || node.Name() != name {
			return false
		}

		return !node.isTheWrittenSideOfAnAssignment() && !node.isTheValueKept(name)
	})
}

func (n Node) isTheValueKept(name string) bool {
	assignment := n.Parent()
	if !assignment.Is("SimpleAssignmentExpression") || assignment.At(1).Node() != n.Node() {
		return false
	}
	if !n.sitsInAConstructor() {
		return false
	}
	written := assignment.At(0)
	if written.Is("SimpleMemberAccessExpression") && written.At(0).Is("ThisExpression") {
		written = written.At(1)
	}

	return written.Is("IdentifierName") && written.Name() == name
}

func (n Node) isTheWrittenSideOfAnAssignment() bool {
	written := n
	if written.Parent().Is("SimpleMemberAccessExpression") && written.Parent().At(0).Is("ThisExpression") {
		written = written.Parent()
	}

	return written.Parent().Is("SimpleAssignmentExpression") && written.Parent().At(0).Node() == written.Node()
}

func (n Node) sitsInAConstructor() bool {
	for held := n.Parent(); held.Exists(); held = held.Parent() {
		if held.Is("ConstructorDeclaration") {
			return true
		}
	}

	return false
}

func (n Node) FilledParameter() (string, bool) {
	if !n.Is("MethodDeclaration") || !n.ReturnsNothing() {
		return "", false
	}
	for _, parameter := range n.firstChild("ParameterList").Children() {
		if !parameter.Is("Parameter") || parameter.hasAttribute() || parameter.isPassedBack() {
			continue
		}
		if n.writesAPropertyOf(parameter.Name()) {
			return parameter.Name(), true
		}
	}

	return "", false
}

func (n Node) ReturnsNothing() bool {
	returned := n.Child("ReturnType")

	return returned.Is("PredefinedType") && returned.Name() == "void" || slices.Contains([]string{"Task", "ValueTask"}, returned.Name())
}

func (n Node) isPassedBack() bool {
	return n.HasModifier("out") || n.HasModifier("ref")
}

func (n Node) writesAPropertyOf(name string) bool {
	return slices.ContainsFunc(n.Match.Descendants(), func(match engine.Match) bool {
		node := Node{Match: match}
		if !node.Is("SimpleAssignmentExpression") {
			return false
		}
		written := node.At(0)

		return written.Is("SimpleMemberAccessExpression") && written.At(0).Is("IdentifierName") && written.At(0).Name() == name
	})
}

func (n Node) ArgumentIsReadAfterTheCall(at int) bool {
	arguments := n.Arguments()
	if at < 0 || at >= len(arguments) || !arguments[at].Is("IdentifierName") {
		return false
	}
	handed := arguments[at]
	method := n.EnclosingFunction()

	return slices.ContainsFunc(method.Match.Descendants(), func(match engine.Match) bool {
		return Node{Match: match}.readsTheNameAfter(handed)
	})
}

func (n Node) readsTheNameAfter(handed Node) bool {
	span, handedSpan := n.Node().Span, handed.Node().Span
	if !n.Is("IdentifierName") || n.Name() != handed.Name() || span.Start <= handedSpan.Start {
		return false
	}
	if !n.statementSharesABlockWith(handed) {
		return false
	}

	return n.Parent().Is("SimpleMemberAccessExpression") || n.Parent().Is("ReturnStatement")
}

func (n Node) statementSharesABlockWith(other Node) bool {
	mine, theirs := n.ownStatement(), other.ownStatement()

	return mine.Exists() && theirs.Exists() && mine.Parent().Node() == theirs.Parent().Node()
}

func (n Node) ownStatement() Node {
	for held := n; held.Exists(); held = held.Parent() {
		if held.Parent().Is("Block") {
			return held
		}
	}

	return Node{}
}

func CallsBySymbol(codebase *engine.Codebase) map[string][]Node {
	calls := map[string][]Node{}
	for _, match := range In(codebase).WhereCall().Get() {
		call := Node{Match: match}
		if symbol := call.Target().Symbol(); symbol != "" {
			calls[symbol] = append(calls[symbol], call)
		}
	}

	return calls
}

func (n Node) ParameterPosition(name string) int {
	for at, parameter := range n.firstChild("ParameterList").Children() {
		if parameter.Is("Parameter") && parameter.Name() == name {
			return at
		}
	}

	return -1
}

var mutableCollections = []string{"List", "Dictionary", "HashSet", "SortedSet", "SortedDictionary", "Queue", "Stack", "LinkedList", "Collection", "ObservableCollection", "IList", "IDictionary", "ISet", "ICollection"}

func (n Node) HandsOutItsOwnCollection() bool {
	written := n.Child("Type")
	if !written.Exists() {
		written = n.Child("ReturnType")
	}
	if !n.Is("PropertyDeclaration", "MethodDeclaration") || !slices.Contains(mutableCollections, written.Name()) {
		return false
	}
	returned := n.handedBack()
	if !returned.Exists() {
		return false
	}
	if n.EnclosingType().isAChainThatBuildsIt(n) {
		return false
	}

	return n.EnclosingType().keepsACollectionIn(returned.Name())
}

func (n Node) isAChainThatBuildsIt(getter Node) bool {
	own, chained := 0, 0
	for _, member := range n.Descendants() {
		if !member.Is("MethodDeclaration", "PropertyDeclaration") || member.Node() == getter.Node() {
			continue
		}
		own++
		if member.Child("ReturnType").Name() == n.Name() || member.Child("Type").Name() == n.Name() {
			chained++
		}
	}

	return chained > 1 && chained == own
}

func (n Node) handedBack() Node {
	returned := n.returnedWhole()
	if returned.Is("SimpleMemberAccessExpression") && returned.At(0).Is("ThisExpression") {
		return returned.At(1)
	}
	if returned.Is("IdentifierName") {
		return returned
	}

	return Node{}
}

func (n Node) returnedWhole() Node {
	if arrow := n.firstChild("ArrowExpressionClause"); arrow.Exists() {
		return arrow.At(0)
	}
	body := n.firstChild("Block")
	statements := body.Children()
	if len(statements) != 1 || !statements[0].Is("ReturnStatement") {
		return Node{}
	}

	return statements[0].Expressions()[0]
}

func (n Node) keepsACollectionIn(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(member Node) bool {
		return slices.ContainsFunc(member.Declarators(), func(declarator Node) bool {
			return declarator.Name() == name && declarator.buildsItsOwn()
		})
	})
}

func (n Node) buildsItsOwn() bool {
	value := n.firstChild("EqualsValueClause")

	return value.Exists() && value.At(0).Is("ObjectCreationExpression", "ImplicitObjectCreationExpression", "CollectionExpression", "ArrayCreationExpression", "ImplicitArrayCreationExpression")
}

func (n Node) GuardsWhatTheTypeRulesOut(declared func(string) bool) bool {
	tested, compares := n.comparedWithNull()
	if !compares {
		return false
	}
	held := tested.Type()
	if !held.Exists() || held.IsNullable() || held.IsValueType() {
		return false
	}
	if !declared(strings.TrimSuffix(held.Name(), "?")) {
		return false
	}

	if n.onlyFailsFast() || n.EnclosingFunction().IsInherited() {
		return false
	}

	return n.namesSomethingDeclaredHere(tested)
}

func (n Node) onlyFailsFast() bool {
	guard := n.Parent()
	if !guard.Is("IfStatement") {
		return false
	}
	taken := guard.Children()
	if len(taken) == 0 {
		return false
	}
	body := taken[0]
	if body.Is("Block") && len(body.Children()) == 1 {
		body = body.Children()[0]
	}

	return body.Is("ThrowStatement")
}

func (n Node) comparedWithNull() (Node, bool) {
	if !n.Is("EqualsExpression", "NotEqualsExpression") {
		return Node{}, false
	}
	left, right := n.At(0), n.At(1)
	if right.Is("NullLiteralExpression") {
		return left, true
	}
	if left.Is("NullLiteralExpression") {
		return right, true
	}

	return Node{}, false
}

func (n Node) namesSomethingDeclaredHere(tested Node) bool {
	name := tested.Name()
	if tested.Is("SimpleMemberAccessExpression") && tested.At(0).Is("ThisExpression") {
		name = tested.At(1).Name()
	} else if !tested.Is("IdentifierName") {
		return false
	}

	return n.EnclosingFunction().takesAParameterNamed(name) || n.EnclosingType().declaresAMemberNamed(name)
}

func (n Node) takesAParameterNamed(name string) bool {
	return slices.ContainsFunc(n.firstChild("ParameterList").Children(), func(parameter Node) bool {
		return parameter.Is("Parameter") && parameter.Name() == name
	})
}

func (n Node) declaresAMemberNamed(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(member Node) bool {
		if member.Is("PropertyDeclaration") {
			return member.Name() == name
		}

		return member.DeclaresVariableNamed(name)
	})
}

var taskTypes = []string{"System.Threading.Tasks.Task", "System.Threading.Tasks.ValueTask"}

func (n Node) BlocksOnATask() bool {
	blocked, blocks := n.taskItBlocksOn()
	if !blocks || !blocked.isATask() {
		return false
	}
	if n.sitsWhereNothingCanDeadlock() {
		return false
	}

	return !blocked.isCompletedWhereItStands()
}

func (n Node) taskItBlocksOn() (Node, bool) {
	if n.Is("SimpleMemberAccessExpression") && n.At(1).Name() == "Result" {
		return n.At(0), true
	}
	if !n.IsCall() || !n.At(0).Is("SimpleMemberAccessExpression") {
		return Node{}, false
	}
	callee := n.At(0)
	if callee.At(1).Name() == "Wait" {
		return callee.At(0), true
	}
	if callee.At(1).Name() != "GetResult" || !callee.At(0).IsCall() {
		return Node{}, false
	}
	awaiter := callee.At(0).At(0)
	if !awaiter.Is("SimpleMemberAccessExpression") || awaiter.At(1).Name() != "GetAwaiter" {
		return Node{}, false
	}

	return awaiter.At(0), true
}

func (n Node) isATask() bool {
	held := strings.TrimSuffix(n.Type().Name(), "?")

	return slices.ContainsFunc(taskTypes, func(task string) bool {
		return held == "global::"+task || strings.HasPrefix(held, "global::"+task+"<")
	})
}

func (n Node) sitsWhereNothingCanDeadlock() bool {
	for held := n.Parent(); held.Exists(); held = held.Parent() {
		if held.Is("ConstructorDeclaration") {
			return true
		}
		if held.Is("MethodDeclaration") {
			return held.Name() == "Main" && held.HasModifier("static")
		}
	}

	return false
}

func (n Node) isCompletedWhereItStands() bool {
	if n.Is("SimpleMemberAccessExpression") && n.At(1).Name() == "CompletedTask" {
		return true
	}

	return n.IsCall() && n.At(0).Is("SimpleMemberAccessExpression") && slices.Contains([]string{"FromResult", "FromException", "FromCanceled"}, n.At(0).At(1).Name())
}
