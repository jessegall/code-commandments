package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
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

// AddsNothing says whether the fallback is the value that leaves an addition as it was: a zero handed to a sum,
// `+=` or an increment, where "no figure" and "add nothing" are the same thing. The absence is not papered over by
// a value the domain never had; it is left out of the total, which is what adding zero does.
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

// AnswerIsAccumulated says whether what the call answers goes straight into a sum: added to something, added into
// something, or handed to a call that increments.
//
// This is the use AddsNothing already pardons where the zero is written at the site, one step further out. A
// method that answers zero where a lookup missed is indistinguishable from one that measured zero, and at a site
// that only adds the answer that is true by construction rather than by accident: the zero contributes nothing
// either way, so there is nothing the reader could have been told.
func (n Node) AnswerIsAccumulated() bool {
	answer := n.OutermostParentheses()
	taken := answer.Parent()

	if taken.Is("AddExpression", "AddAssignmentExpression") {
		return sums(taken)
	}

	return taken.Is("Argument") && strings.Contains(callOf(taken), "Increment")
}

// sums says whether the addition adds numbers, where C# writes a string's concatenation with the same `+`: a zero
// joined into a sentence is read by whoever reads the sentence, which is the sin this rule names, and a zero added
// to a number is not read at all.
func sums(addition Node) bool {
	return slices.Contains(numbers, strings.TrimPrefix(addition.Type().Name(), "global::System."))
}

// numbers are the types an addition of numbers answers, as the bridge names them.
var numbers = []string{
	"int", "long", "short", "byte", "sbyte", "uint", "ulong", "ushort", "float", "double", "decimal",
	"Int32", "Int64", "Int16", "Byte", "SByte", "UInt32", "UInt64", "UInt16", "Single", "Double", "Decimal",
}

// callOf is the name of the call an argument is handed to.
func callOf(argument Node) string {
	call := argument.Parent().Parent()
	if !call.IsCall() {
		return ""
	}

	return call.CalleeName()
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

			return caught == "" || !handsOver(created, caught)
		}
	}

	return false
}

// FillsAnOutsideContract says whether the node sits in a member that fills a declaration the project does not
// hold: every interface it implements and every base member it overrides was written somewhere else.
//
// Such a member cannot return a task, because the declaration that would have to change is not the project's. A
// JSON converter's Read, a configuration provider's Load, a Dispose: a blocking wait there is the only way a
// synchronous caller reaches asynchronous work.
//
// A contract the project declares ITSELF is a different matter, and asking only whether a member is inherited
// cannot tell the two apart. A project's own interface can be made to return a task, and a method that blocks
// behind its own interface is exactly the sin this rule is about; judging jellyfin showed a rule that asked only
// about inheritance going quiet on thirteen of twenty-one real findings, all of them behind interfaces jellyfin
// wrote. Asking the scan who declares the interface is no better, since judging one folder of a solution leaves
// the rest of it looking like a stranger: the compiler decides this one, and the bridge writes what it decided.
func (n Node) FillsAnOutsideContract() bool {
	for _, ancestor := range n.Ancestors() {
		if ancestor.IsFunction() {
			return ancestor.FillsOutsideTheSolution()
		}
	}

	return false
}

// handsOver says whether the thrown expression hands the caught exception on: as an argument of its own
// construction, `new Failed(message, ex)`, or as an argument of any call inside it, which is how a framework that
// offers no inner-exception constructor takes a cause — `ErrorBuilder.New().SetMessage(…).SetException(ex)`.
//
// The exception OBJECT is what counts. A throw that reads the cause's words, `$"…{ex.Message}"`, keeps the
// sentence a human wrote and loses the stack, the type and everything the exception carried, which is the sin this
// rule is about.
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

// ConvertsToADeclaredType says whether the node sits in a method that reads loose data and answers a type of the
// project, built in that method: the conversion at the edge that a dict-bag finding asks for.
//
// The pardon for a named constructor only covers a factory that sits IN the type it builds, and a mapping often
// cannot: a record read back from a store, a vendor's payload, or a document in a shape no current type holds is
// exactly what the type should not know about. Wherever the conversion lives, a method that ends in one of the
// project's own types has done the work the rule asks for, and the keys it read on the way are the reading.
func (n Node) ConvertsToADeclaredType(program *Program) bool {
	for _, ancestor := range n.Ancestors() {
		if !ancestor.IsFunction() {
			continue
		}

		answered := strings.TrimSuffix(ancestor.ReturnType().Name(), "?")
		if answered == "" {
			return false
		}

		// The return type is written as the code writes it, `ListingKeys`, while a construction carries the whole
		// name the compiler bound, so the construction is what the project is asked about.
		return slices.ContainsFunc(ancestor.OutermostExpressions(), func(expression Node) bool {
			return slices.ContainsFunc(append(expression.Flatten(), expression), func(part Node) bool {
				return builds(part, answered) && program.DeclaresType(part.Type().Name())
			})
		})
	}

	return false
}

// builds says whether the expression constructs the type the written name says: a name the compiler bound ends in
// the name the code wrote, and a construction left to the compiler is the answered type by definition.
func builds(expression Node, named string) bool {
	if expression.Is("ImplicitObjectCreationExpression") {
		return true
	}

	return expression.Is("ObjectCreationExpression") && strings.HasSuffix(expression.Type().Name(), "."+named)
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

// IsInTest says whether the node is written in a file of a test project: code that exercises the product rather
// than being it.
func (n Node) IsInTest() bool {
	return n.Exists() && n.Source().Test
}

// collectionMutators are the methods that change a collection's contents or its order in place. Calling one on
// the collection a foreach is walking moves it under the enumerator, which then throws on the next read.
var collectionMutators = []string{"Add", "AddRange", "Insert", "InsertRange", "Remove", "RemoveAt", "RemoveAll", "RemoveRange", "Clear", "Sort", "Reverse", "Enqueue", "Dequeue", "Push", "Pop", "TryAdd", "TryRemove"}

// ChangesTheCollectionItsLoopWalks says whether the call changes, in place, the very collection a foreach around
// it is walking: one of the methods that change a collection, on an expression written exactly as that loop's
// own subject, or as a mapping whose keys or values it walks. The enumerator then reads a collection that has
// moved, which is an InvalidOperationException. Walking a copy, `ToList()` or `ToArray()`, is a different
// expression and is left alone, as is a change that leaves the loop the moment it is made.
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

// loopWalking is the foreach around the node that walks the given collection, and whether one does. The node has
// to sit in the loop's body: a change made in the subject itself runs once, before the walk.
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

// collectionWalkedLive is the collection a foreach's subject reads as it goes, and whether it reads one live:
// the collection itself, or a mapping's Keys or Values, which are views onto it rather than copies of it. A
// concurrent collection answers none: its enumerator is a snapshot taken when the walk starts, so changing it
// mid-walk is what the type is for and the pruning loop that does it is correct.
func (n Node) collectionWalkedLive() (Node, bool) {
	if n.Is("SimpleMemberAccessExpression") && slices.Contains([]string{"Keys", "Values"}, n.ReferencedName()) {
		return n.At(0).collectionWalkedLive()
	}
	if strings.Contains(n.Type().Name(), "System.Collections.Concurrent.") {
		return Node{}, false
	}

	return n, n.Is("IdentifierName", "SimpleMemberAccessExpression")
}

// leavesThatLoopAtOnce says whether control cannot reach the loop's enumerator again once the node has run: the
// block holding it ends by leaving, with a return, a throw or a break out of that very loop. A find-and-remove
// that breaks the moment it has removed reads nothing more, so the collection moving costs it nothing.
func (n Node) leavesThatLoopAtOnce(loop Node) bool {
	block := n.statementsHolding()
	if len(block) == 0 {
		return false
	}

	return block[len(block)-1].leaves(loop)
}

// statementsHolding is the statements of the innermost block the node sits in, in source order.
func (n Node) statementsHolding() []Node {
	for held := n; held.Exists(); held = held.Parent() {
		if held.Parent().Is("Block") {
			return held.Parent().Children()
		}
	}

	return nil
}

// leaves says whether the statement hands control out of the loop for good: a return or a throw leaves the
// method around it, and a break leaves the loop it sits in. A continue goes back to the enumerator, so it does
// not leave at all.
func (n Node) leaves(loop Node) bool {
	if n.Is("ReturnStatement", "ThrowStatement", "YieldBreakStatement") {
		return true
	}

	return n.Is("BreakStatement") && n.nearestLoop().Node() == loop.Node()
}

// nearestLoop is the innermost loop around the node; no node outside one.
func (n Node) nearestLoop() Node {
	for loop := n.Parent(); loop.Exists(); loop = loop.Parent() {
		if loop.IsLoop() {
			return loop
		}
	}

	return Node{}
}

// IsUnreadCollaborator says whether the node is a collaborator the type takes and never reads: a private
// readonly field, or a parameter of the type's primary constructor, whose name nothing in the type names
// again. The type asks for something it does not use, and the only way a reader learns that is to search it.
// A partial type answers no, since the part that reads it may be another file; so does a type that holds a
// type of its own, which can read a private field of its outer, and a field something has marked up, which
// tells the framework to write or read it.
func (n Node) IsUnreadCollaborator() bool {
	name, taken := n.collaboratorTaken()
	owner := n.EnclosingType()
	if !taken || owner.HasModifier("partial") || owner.holdsATypeOfItsOwn() {
		return false
	}

	return !owner.namesTheCollaborator(name, n)
}

// collaboratorTaken is the name the type takes a collaborator under, and whether the node takes one: a private
// readonly field of a class type, or a parameter of a primary constructor, which is the same ask in one line.
func (n Node) collaboratorTaken() (string, bool) {
	switch {
	case n.Is("VariableDeclarator") && n.Parent().Parent().Is("FieldDeclaration"):
		field := n.Parent().Parent()
		if !field.HasModifier("private") || !field.HasModifier("readonly") || field.hasAttribute() {
			return "", false
		}
		// A static readonly field is a constant of the type, not something an object was given: an unread one
		// is dead data, which is a different thing to say and a different fix.
		if field.HasModifier("static") {
			return "", false
		}

		return n.Name(), true
	// A record's positional parameters are its public properties: the record exists so that others read them,
	// and it is under no obligation to read them itself.
	case n.Is("Parameter") && n.Parent().Is("ParameterList") && n.Parent().Parent().Is("ClassDeclaration", "StructDeclaration"):
		if n.hasAttribute() {
			return "", false
		}

		return n.Name(), true
	}

	return "", false
}

// hasAttribute says whether anything is written above the declaration in brackets.
func (n Node) hasAttribute() bool {
	return slices.ContainsFunc(n.All(), func(child Node) bool { return child.Is("AttributeList") })
}

// holdsATypeOfItsOwn says whether the type declares a type inside itself, which may read a private field of it.
func (n Node) holdsATypeOfItsOwn() bool {
	return slices.ContainsFunc(n.Children(), func(member Node) bool {
		return member.Is("ClassDeclaration", "RecordDeclaration", "StructDeclaration", "InterfaceDeclaration", "EnumDeclaration")
	})
}

// namesTheCollaborator says whether anything in the type names the collaborator, its own declaration and the
// constructor's write of it aside. A name is an expression, which this engine's own Descendants leaves out, so
// the whole tree below the type is walked.
func (n Node) namesTheCollaborator(name string, declaration Node) bool {
	return slices.ContainsFunc(n.Match.Descendants(), func(match engine.Match) bool {
		node := Node{Match: match}
		if node.Node() == declaration.Node() || !node.Is("IdentifierName") || node.Name() != name {
			return false
		}

		return !node.isTheWrittenSideOfAnAssignment() && !node.isTheValueKept(name)
	})
}

// isTheValueKept says whether the name is the value a constructor keeps under the same name: the parameter in
// `this.mailer = mailer`, which is how the field gets its collaborator and not a read of the field at all. It
// has to be a constructor's own assignment: `new Session { Headers = Headers }` says the same words elsewhere
// and is a read.
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

// isTheWrittenSideOfAnAssignment says whether the name is what an assignment writes to, directly or through
// `this`, rather than a value anything reads.
func (n Node) isTheWrittenSideOfAnAssignment() bool {
	written := n
	if written.Parent().Is("SimpleMemberAccessExpression") && written.Parent().At(0).Is("ThisExpression") {
		written = written.Parent()
	}

	return written.Parent().Is("SimpleAssignmentExpression") && written.Parent().At(0).Node() == written.Node()
}

// sitsInAConstructor says whether a constructor of the type holds the node.
func (n Node) sitsInAConstructor() bool {
	for held := n.Parent(); held.Exists(); held = held.Parent() {
		if held.Is("ConstructorDeclaration") {
			return true
		}
	}

	return false
}

// FilledParameter is the parameter a method fills and never gives back, and whether it fills one: a parameter
// whose own property the body writes, in a method that returns nothing. The signature then says the object goes
// in and says nothing about it coming out changed, so the caller has to know.
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

// ReturnsNothing says whether the method's declared return type is void, Task or ValueTask: nothing a caller
// can read an answer out of.
func (n Node) ReturnsNothing() bool {
	returned := n.Child("ReturnType")

	return returned.Is("PredefinedType") && returned.Name() == "void" || slices.Contains([]string{"Task", "ValueTask"}, returned.Name())
}

// isPassedBack says whether the parameter already says the value comes back out: `out` and `ref` are the
// language's own way of saying it, and a signature that says so is no part of this.
func (n Node) isPassedBack() bool {
	return n.HasModifier("out") || n.HasModifier("ref")
}

// writesAPropertyOf says whether the method writes a property of the parameter the name holds.
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

// ArgumentIsReadAfterTheCall says whether the name the call hands over at the position is read again later in
// the same block: a property of it, or the name handed back. The value the callee wrote into it is what the
// caller came back for, which is the whole shape of a filled parameter.
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

// readsTheNameAfter says whether the node reads what the callee wrote into the handed-over name, later in the
// same block: a property of it, or the name handed back. A read in another branch may be unreachable from the
// call, and holding a method to that would be guessing at the flow rather than reading it.
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

// statementSharesABlockWith says whether the two nodes' own statements sit in the same block, so one really
// does run after the other.
func (n Node) statementSharesABlockWith(other Node) bool {
	mine, theirs := n.ownStatement(), other.ownStatement()

	return mine.Exists() && theirs.Exists() && mine.Parent().Node() == theirs.Parent().Node()
}

// ownStatement is the node itself or the ancestor of it that a block holds directly.
func (n Node) ownStatement() Node {
	for held := n; held.Exists(); held = held.Parent() {
		if held.Parent().Is("Block") {
			return held
		}
	}

	return Node{}
}

// CallsBySymbol is every call in the codebase, grouped by the symbol of the method it reaches. The engine keeps
// how MANY callers a method has; a rule that asks what a caller does with what it handed over needs the calls
// themselves, and one walk answers it for every method at once.
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

// ParameterPosition is where the named parameter sits in the method's list, counting from zero; -1 for a name
// it does not declare.
func (n Node) ParameterPosition(name string) int {
	for at, parameter := range n.firstChild("ParameterList").Children() {
		if parameter.Is("Parameter") && parameter.Name() == name {
			return at
		}
	}

	return -1
}

// mutableCollections are the types that say "a collection anyone may change": handing one of a class's own back
// is handing the insides out. IReadOnlyList, IReadOnlyDictionary, IEnumerable and ImmutableArray say the
// opposite and are how a getter gives its collection away safely.
var mutableCollections = []string{"List", "Dictionary", "HashSet", "SortedSet", "SortedDictionary", "Queue", "Stack", "LinkedList", "Collection", "ObservableCollection", "IList", "IDictionary", "ISet", "ICollection"}

// HandsOutItsOwnCollection says whether the node is a property or a method whose whole body returns one of its
// type's own mutable collections, untouched: every caller then holds the insides and may add to or take from
// them without asking, so the type cannot keep its own invariant and does not know when it is broken.
func (n Node) HandsOutItsOwnCollection() bool {
	// A property writes its type in the `Type` slot and a method in `ReturnType`; both answer with the one the
	// node actually carries.
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

// isAChainThatBuildsIt says whether the type is a chain whose only purpose is to assemble the collection the
// node hands over: every other method of it answers with the type itself, and more than one does, so there is
// nothing a caller can do with the type but chain into it and then take what it built. Handing the collection
// over is the whole point, and the type keeps no invariant after it. One chainable method is a wither on a
// value object, which does keep one, so a chain takes two.
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

// handedBack is the field a property or method hands back whole, and nothing else: `this.notes`, or `notes`
// where a field of that name is the only thing it could be. No node for a body that does anything at all with
// what it returns, a copy among it.
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

// returnedWhole is the one expression a property or method answers with, whether written as an arrow or as a
// body whose only statement is a return; no node for anything longer.
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

// keepsACollectionIn says whether the type declares a field of that name holding a collection of its own: one
// it builds itself, so what a getter hands out is the type's own insides and not something it was lent.
func (n Node) keepsACollectionIn(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(member Node) bool {
		declarator := member.firstChild("VariableDeclaration").firstChild("VariableDeclarator")

		return member.Is("FieldDeclaration") && declarator.Name() == name && declarator.buildsItsOwn()
	})
}

// buildsItsOwn says whether the field is given a collection the type makes, rather than one handed to it.
func (n Node) buildsItsOwn() bool {
	value := n.firstChild("EqualsValueClause")

	return value.Exists() && value.At(0).Is("ObjectCreationExpression", "ImplicitObjectCreationExpression", "CollectionExpression", "ArrayCreationExpression", "ImplicitArrayCreationExpression")
}

// GuardsWhatTheTypeRulesOut says whether the node compares a value with null that its own declared type has
// already ruled out: a parameter or a field whose type the compiler reads as not nullable, and which is one of
// the project's own types rather than one the scan never saw. One of two things is then true and both are
// worth saying — the type is lying, or the guard is noise someone has to read past.
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

// onlyFailsFast says whether the guard does nothing but throw. A type is a promise the compiler keeps only
// among callers who honour it, and a boundary that asserts its own contract and dies is not pretending the
// impossible can happen; it is refusing to carry on. What this rule is about is a guard that answers the
// impossible with a value.
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

// comparedWithNull is what a comparison tests against null, and whether the node is one: `== null` and
// `!= null` both say it.
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

// namesSomethingDeclaredHere says whether what the comparison tests is something whose type is WRITTEN where
// the reader can see it: a parameter of the method around it, or a field or property of the type it sits in. A
// `var` local's type is inferred and written nowhere, so neither the reader nor this rule can hold anyone to
// it, however the compiler reads it.
func (n Node) namesSomethingDeclaredHere(tested Node) bool {
	name := tested.Name()
	if tested.Is("SimpleMemberAccessExpression") && tested.At(0).Is("ThisExpression") {
		name = tested.At(1).Name()
	} else if !tested.Is("IdentifierName") {
		return false
	}

	return n.EnclosingFunction().takesAParameterNamed(name) || n.EnclosingType().declaresAMemberNamed(name)
}

// takesAParameterNamed says whether the method writes a parameter of that name.
func (n Node) takesAParameterNamed(name string) bool {
	return slices.ContainsFunc(n.firstChild("ParameterList").Children(), func(parameter Node) bool {
		return parameter.Is("Parameter") && parameter.Name() == name
	})
}

// declaresAMemberNamed says whether the type writes a field or a property of that name.
func (n Node) declaresAMemberNamed(name string) bool {
	return slices.ContainsFunc(n.Descendants(), func(member Node) bool {
		if member.Is("PropertyDeclaration") {
			return member.Name() == name
		}

		return member.Is("FieldDeclaration") && member.firstChild("VariableDeclaration").firstChild("VariableDeclarator").Name() == name
	})
}

// taskTypes are the types that stand for work in flight. Reading an answer out of one, rather than awaiting
// it, blocks the thread that is holding the context the work needs to finish on.
var taskTypes = []string{"System.Threading.Tasks.Task", "System.Threading.Tasks.ValueTask"}

// BlocksOnATask says whether the node takes the answer out of work in flight by blocking for it: reading
// `.Result`, calling `.Wait()`, or `.GetAwaiter().GetResult()`, on an expression the compiler says is a task.
// Under load the thread it blocks is the one the work needed, and the deadlock shows up there and nowhere
// else, which is the worst way to find a bug. A constructor and an entry point have no context to deadlock
// against, and a task completed where it stands has nothing left to wait for, so neither is this.
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

// taskItBlocksOn is the expression a blocking read takes its answer from, and whether the node is one.
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

// isATask says whether the compiler reads the expression as work in flight.
func (n Node) isATask() bool {
	held := strings.TrimSuffix(n.Type().Name(), "?")

	return slices.ContainsFunc(taskTypes, func(task string) bool {
		return held == "global::"+task || strings.HasPrefix(held, "global::"+task+"<")
	})
}

// sitsWhereNothingCanDeadlock says whether the node sits where there is no context for the work to need back:
// a constructor, which cannot await, or a program's entry point.
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

// isCompletedWhereItStands says whether the task is finished by the time it is read: `Task.FromResult(x)` and
// `Task.CompletedTask` are done before anyone asks, so taking their answer blocks on nothing.
func (n Node) isCompletedWhereItStands() bool {
	if n.Is("SimpleMemberAccessExpression") && n.At(1).Name() == "CompletedTask" {
		return true
	}

	return n.IsCall() && n.At(0).Is("SimpleMemberAccessExpression") && slices.Contains([]string{"FromResult", "FromException", "FromCanceled"}, n.At(0).At(1).Name())
}
