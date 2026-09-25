package csharp

import (
	"slices"
	"strings"
)

// Ancestors is every node around this one, the nearest first.
func (n Node) Ancestors() []Node {
	var ancestors []Node
	for around := n.Parent(); around.Exists(); around = around.Parent() {
		ancestors = append(ancestors, around)
	}

	return ancestors
}

// EnclosingFunction is the nearest function-like around the node: a member, an accessor, a local function, a
// lambda; no node outside one.
func (n Node) EnclosingFunction() Node {
	for _, around := range n.Ancestors() {
		if around.IsFunction() {
			return around
		}
	}

	return Node{}
}

// EnclosingType is the nearest class, record, struct or interface around the node; no node outside one.
func (n Node) EnclosingType() Node {
	for _, around := range n.Ancestors() {
		if around.Is("ClassDeclaration", "RecordDeclaration", "StructDeclaration", "RecordStructDeclaration", "InterfaceDeclaration") {
			return around
		}
	}

	return Node{}
}

// DeclaredName is the name the node declares or reads; an accessor answers for the property, indexer or event it
// belongs to.
func (n Node) DeclaredName() string {
	if n.Name() != "" || !strings.HasSuffix(n.Kind(), "AccessorDeclaration") {
		return n.Name()
	}
	for _, member := range n.Ancestors() {
		if member.Is("PropertyDeclaration", "IndexerDeclaration", "EventDeclaration") && member.Name() != "" {
			return member.Name()
		}
	}

	return ""
}

// IsStub says whether the body only says it is not written yet: `throw new NotImplementedException()`, as a block
// or an expression body.
func (n Node) IsStub() bool {
	return n.FunctionBody().thrown().Type().Name() == "global::System.NotImplementedException"
}

// thrown is what the body throws when throwing is all it does: `{ throw …; }` or `=> throw …`.
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

// IsElseIf says whether the `if` stands as another `if`'s `else`, one rung of its ladder.
func (n Node) IsElseIf() bool {
	return n.Is("IfStatement") && n.Parent().Is("ElseClause")
}

// HasRedundantElse says whether the `if`'s branch already left, yet it carries an `else`. An `if` whose `else` is
// the next rung is a ladder, not a guard.
func (n Node) HasRedundantElse() bool {
	if !n.Is("IfStatement") || n.IsElseIf() {
		return false
	}
	children := n.Children()
	if len(children) < 2 || children[1].Children()[0].Is("IfStatement") {
		return false
	}
	statements := []Node{children[0]}
	if children[0].Is("Block") {
		statements = children[0].Children()
	}

	return len(statements) > 0 && statements[len(statements)-1].IsBailOut()
}

// SubjectLadderLength is how many rungs the `if` ladder has when every one compares the same subject with a
// constant; zero for a ladder whose rungs test anything else, and for an `else if` itself.
func (n Node) SubjectLadderLength() int {
	if !n.Is("IfStatement") || n.IsElseIf() {
		return 0
	}
	rungs := n.rungs()
	var subjects []string
	for _, rung := range rungs {
		subject := rung.At(0).ComparisonSubject()
		if !subject.Exists() {
			return 0
		}
		if hash := ExpressionHash(subject); !slices.Contains(subjects, hash) {
			subjects = append(subjects, hash)
		}
	}
	if len(subjects) != 1 {
		return 0
	}

	return len(rungs)
}

// rungs is the `if` statements of the ladder the `if` opens: itself, then each `else if` in turn.
func (n Node) rungs() []Node {
	children := n.Children()
	if len(children) < 2 {
		return []Node{n}
	}
	if next := children[1].Children(); len(next) > 0 && next[0].Is("IfStatement") {
		return append([]Node{n}, next[0].rungs()...)
	}

	return []Node{n}
}

// IsSoleLoopBodyGuard says whether the `if`, with no `else`, is the whole body of a loop, burying two statements or
// more a level deep behind its condition. A body that ends by leaving the loop picks the one item it wanted.
func (n Node) IsSoleLoopBodyGuard() bool {
	children := n.Children()
	if !n.Is("IfStatement") || len(children) != 1 {
		return false
	}
	work := []Node{children[0]}
	if children[0].Is("Block") {
		work = children[0].Children()
	}
	if len(work) < 2 || work[len(work)-1].IsBailOut() {
		return false
	}
	holder, loop := Node{}, n.Parent()
	if loop.Is("Block") {
		holder, loop = loop, loop.Parent()
	}

	return loop.IsLoop() && (!holder.Exists() || len(holder.Children()) == 1)
}

// FillsArgument says whether the expression is handed straight to a call as one of its arguments.
func (n Node) FillsArgument() bool {
	return n.Parent().Is("Argument")
}

// OutermostParentheses is the expression as its surroundings see it: wrapped in every pair of parentheses around it.
func (n Node) OutermostParentheses() Node {
	inner := n
	for inner.Parent().Is("ParenthesizedExpression") {
		inner = inner.Parent()
	}

	return inner
}

// IsComparedToItsFallback says whether the fallback expression, `name ?? ""`, is compared with `==` or `!=` against
// the very value it falls back to, so the fallback only ever cancels itself.
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

// IsWrappingWithoutCause says whether the `throw new …` is inside a catch that it does not hand the caught
// exception on to, so the original stack trace is lost. A throw in a lambda inside the catch runs later, outside it.
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

			return caught == "" || !slices.ContainsFunc(created.Arguments(), func(argument Node) bool {
				return argument.Is("IdentifierName") && argument.Name() == caught
			})
		}
	}

	return false
}

// IsConstructorSideEffect says whether the call is a constructor telling a collaborator it was handed to act: a
// method called on one of its parameters, or on a field it filled from one, with the answer thrown away. A call
// tried in a `try` that handles its failure is not.
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

// collaborators is the names in a constructor that stand for something it was handed: its parameters, and the
// fields it fills from them.
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

// heldName is the name the expression reads: `printer` for `printer` and for `this.printer`; empty otherwise.
func heldName(expression Node) string {
	switch {
	case expression.Is("IdentifierName"):
		return expression.Name()
	case expression.Is("SimpleMemberAccessExpression") && expression.At(0).Is("ThisExpression"):
		return expression.At(1).Name()
	}

	return ""
}

// IsWritingStaticState says whether the expression writes a static field of its own type that is neither
// `readonly` nor `const`, from a method or accessor rather than the static constructor or the field's initializer.
func (n Node) IsWritingStaticState() bool {
	scope := n.EnclosingFunction()
	if !n.IsWrite() || !scope.Exists() || (scope.Is("ConstructorDeclaration") && scope.HasModifier("static")) {
		return false
	}
	owner := n.EnclosingType()

	return owner.Exists() && slices.Contains(owner.mutableStaticFields(), fieldWritten(n.At(0), owner, scope))
}

// fieldWritten is the field of the type the target names, `hits` or `Counter.hits`; empty for anything else,
// including a local or parameter of the scope's that shadows it.
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

// mutableStaticFields is the static fields the type declares that anything may overwrite.
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

// IsBuriedThrow says whether the `?? throw` is buried in the work, handed to a call or the thing a member is read
// or called on, rather than assigned or returned as the guard it is.
func (n Node) IsBuriedThrow() bool {
	if !n.Is("CoalesceExpression") || !n.At(1).Is("ThrowExpression") {
		return false
	}
	inner := n.OutermostParentheses()
	around := inner.Parent()

	return around.Is("Argument") || (around.Is("SimpleMemberAccessExpression", "ConditionalAccessExpression") && around.At(0).Node() == inner.Node())
}

// IsGroupTestRoot says whether the node is the whole of a group test: the outermost `||` of a chain, or an `or`
// pattern a value is tested against with `is`.
func (n Node) IsGroupTestRoot() bool {
	switch {
	case n.Is("LogicalOrExpression"):
		return !n.Parent().Is("LogicalOrExpression")
	case n.Is("OrPattern"):
		return n.Parent().Is("IsPatternExpression")
	}

	return false
}

// IsWritingRecordState says whether the expression writes the record it sits in after construction. A write in a
// constructor, an `init` accessor or an initializer builds a record rather than changing one, and `??=` only fills
// a cache nobody has read yet.
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

// IsRecordSetter says whether the node is a `set` accessor on a property of a record.
func (n Node) IsRecordSetter() bool {
	return n.Is("SetAccessorDeclaration") && n.EnclosingType().IsRecord()
}

// IsNullObjectOfItsType says whether the value is the instance a type keeps of itself as its Null Object: the
// initial value of one of its own `static` fields or properties.
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

// IsOutermostAnd says whether the node is the whole of a `&&` chain, not one side of a larger one.
func (n Node) IsOutermostAnd() bool {
	return n.Is("LogicalAndExpression") && !n.OutermostParentheses().Parent().Is("LogicalAndExpression")
}

// IsStoredValue says whether the expression is the value a variable is given or assigned: stored rather than asked.
func (n Node) IsStoredValue() bool {
	return n.OutermostParentheses().Parent().Is("EqualsValueClause", "SimpleAssignmentExpression")
}

// StartsAppendLineRun says whether the statement is the first of a run of the given number or more `AppendLine`
// statements on one builder, the fixed number of them writing text written into the source.
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

// IsReturned says whether the expression is what its member hands back: the value of a `return`, or of an
// expression body.
func (n Node) IsReturned() bool {
	return n.OutermostParentheses().Parent().Is("ReturnStatement", "ArrowExpressionClause")
}

// IsConditionalBranch says whether the expression is a branch of a conditional expression, so a chain of them is
// reported once, at its outermost.
func (n Node) IsConditionalBranch() bool {
	inner := n.OutermostParentheses()
	around := inner.Parent()

	return around.Is("ConditionalExpression") && around.At(0).Node() != inner.Node()
}

// IsInventingOnMiss says whether the member answers a lookup miss with an invented empty value: every value it
// returns is either `""`/`0`/`false`, or what a dictionary lookup found, the lookup itself or the `out` variable a
// `TryGetValue` in the member filled.
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

// lookedUpNames is the names of the `out` variables a `TryGetValue` among the nodes fills.
func lookedUpNames(scope []Node) []string {
	var names []string
	for _, node := range scope {
		for _, expression := range node.Expressions() {
			for _, lookup := range expression.Flatten() {
				if !lookup.IsLookup() || !lookup.IsCall() {
					continue
				}
				for _, part := range lookup.Flatten() {
					if !part.Is("DeclarationExpression") {
						continue
					}
					for _, designation := range part.All() {
						if designation.Is("SingleVariableDesignation") && designation.Name() != "" {
							names = append(names, designation.Name())
						}
					}
				}
			}
		}
	}

	return names
}

// IsWithinNamedConstructor says whether the node is written where a type builds itself from loose data: a
// constructor, or a static factory whose declared return type is the type it sits in.
func (n Node) IsWithinNamedConstructor() bool {
	for _, member := range n.Ancestors() {
		if member.Is("ConstructorDeclaration", "MethodDeclaration") {
			return n.isNamedConstructorOf(member)
		}
	}

	return false
}

// IsNamedConstructor says whether the member is a constructor, or a static factory whose declared return type is
// the type it sits in.
func (n Node) IsNamedConstructor() bool {
	return n.isNamedConstructorOf(n)
}

func (n Node) isNamedConstructorOf(member Node) bool {
	owner := n.EnclosingType()

	return member.Is("ConstructorDeclaration") ||
		(member.HasModifier("static") && owner.Exists() && member.writtenTypeNode().Exists() && member.writtenTypeNode().Name() == owner.Name())
}

// DefaultedNameTestedForBlankness says whether the blank-defaulted parameter or property is asked, in its own
// scope, whether it is blank: the question that proves the blank stands in for absence.
func (n Node) DefaultedNameTestedForBlankness() bool {
	owner := n.EnclosingType()
	if n.Is("Parameter") {
		owner = n.EnclosingFunction()
	}

	return owner.Exists() && slices.ContainsFunc(owner.expressionParts(), func(expression Node) bool { return expression.TestsBlanknessOf(n.Name()) })
}

// Owner is what the node belongs to, the type it is declared in or its file for a top-level function, named by
// where it is, so two of them compare.
func (n Node) Owner() string {
	if owner := n.EnclosingType(); owner.Exists() {
		return n.File() + "::" + owner.Name()
	}

	return n.File()
}

// IsWithinTryMethod says whether the node sits in a method that reports failure through its answer: one that
// hands its results back through an `out` parameter, the `TryParse` shape, where `false` is the failure.
func (n Node) IsWithinTryMethod() bool {
	for _, method := range n.Ancestors() {
		if method.Is("MethodDeclaration", "LocalFunctionStatement") {
			return slices.ContainsFunc(method.parameters(), func(parameter Node) bool { return parameter.HasModifier("out") })
		}
	}

	return false
}

// before is the members declared ahead of this one in the node around it.
func (n Node) before() []Node {
	siblings := n.Parent().All()
	at := slices.IndexFunc(siblings, func(sibling Node) bool { return sibling.Node() == n.Node() })
	if at < 0 {
		return nil
	}

	return siblings[:at]
}

// IsMemberAfterMethod says whether the state member is declared below one of its type's constructors or methods.
// An interface declares a contract, not state.
func (n Node) IsMemberAfterMethod() bool {
	if !n.IsStateMember() || !n.Parent().Exists() || n.Parent().Is("InterfaceDeclaration") {
		return false
	}

	return slices.ContainsFunc(n.before(), func(member Node) bool { return member.Is("MethodDeclaration", "ConstructorDeclaration") })
}

// IsMemberOutOfOrder says whether the constant is declared below one of its type's instance fields or stored
// properties: the top of the type read out of order.
func (n Node) IsMemberOutOfOrder() bool {
	return n.IsConstantMember() && slices.ContainsFunc(n.before(), Node.IsInstanceStateMember)
}

// IsReadingOwnDictionary says whether the keyed read reads a dictionary the code in hand owns, a parameter or a
// local of a function it sits in, rather than one reached through another object.
func (n Node) IsReadingOwnDictionary() bool {
	var names []string
	for _, function := range n.Ancestors() {
		if function.IsFunction() {
			names = append(names, function.OwnNames()...)
		}
	}

	return n.ReadsDictionaryNamed(names)
}

// IsAssignedTo says whether the indexer is being assigned to: a write, not a read.
func (n Node) IsAssignedTo() bool {
	around := n.Parent()

	return strings.HasSuffix(around.Kind(), "AssignmentExpression") && around.At(0).Node() == n.Node()
}

// IsWithinOverride says whether the node sits in a member that overrides or implements a contract, whose
// signature and whatever data it is handed the contract decided.
func (n Node) IsWithinOverride() bool {
	return slices.ContainsFunc(n.Ancestors(), Node.IsInherited)
}

// IsBuildingAnObject says whether the node feeds straight into an object being created within the same function:
// reading loose data there is converting it, at the edge.
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

// ComparedLiterals is the string literals the node dispatches a value on: a `switch`'s case labels or arms, or the
// rungs of an `if` ladder; none for anything else.
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
		for _, rung := range n.rungs() {
			values = append(values, rung.At(0))
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

// BranchingDepth is how many choices the node sits inside within its function: each `if`, loop or `switch` whose
// body holds it, an `else if` a rung of the ladder it continues, not a level of its own.
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
