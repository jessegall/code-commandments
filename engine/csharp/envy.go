package csharp

import (
	"slices"
	"sort"
	"strings"
	"unicode"
)

// keyTypes are the types that read as a lookup key: an identity, not a collaborator.
var keyTypes = []string{"global::System.String", "global::System.Int32", "global::System.Int64", "global::System.Guid"}

// CapturedLocal is the local the expression's result is kept in, `var node = …` or `node = …`, through a `!`,
// parentheses or the left of a `??`; empty when the result goes anywhere else.
func (n Node) CapturedLocal() string {
	node := n
	for parent := n.Parent(); parent.Exists(); parent = parent.Parent() {
		if parent.Is("SuppressNullableWarningExpression", "ParenthesizedExpression") || (parent.Is("CoalesceExpression") && parent.At(0).Node() == node.Node()) {
			node = parent
			continue
		}
		switch {
		case parent.Is("EqualsValueClause"):
			return parent.Parent().Name()
		case parent.Is("SimpleAssignmentExpression") && parent.At(1).Node() == node.Node() && parent.At(0).Is("IdentifierName"):
			return parent.At(0).Name()
		}

		return ""
	}

	return ""
}

// IsOnlyReadThrough says whether the name the root reads is used, among the reads, only to read properties off it:
// never handed on whole, never called a method on. The root itself is not one of those uses.
func (n Node) IsOnlyReadThrough(reads []Node) bool {
	return !slices.ContainsFunc(reads, func(use Node) bool {
		if use.Node() == n.Node() || !use.Is("IdentifierName") || use.Name() != n.Name() {
			return false
		}
		around := use.Parent()
		readThrough := around.Is("SimpleMemberAccessExpression") && around.At(0).Node() == use.Node() &&
			!(around.Parent().IsCall() && around.Parent().At(0).Node() == around.Node())

		return !readThrough
	})
}

// UnpacksTargetFromContainerParam says whether the method looks its target up in a container parameter by a key
// parameter, keeps what it found in a local, and uses the container for nothing but reading its properties, while
// being more than the resolver. Only a container the codebase declares counts.
func (n Node) UnpacksTargetFromContainerParam(program *Program) bool {
	body := n.FunctionBody()
	if !n.Is("MethodDeclaration") || !body.Exists() {
		return false
	}
	var owners, keys []string
	for _, parameter := range n.Parameters() {
		held := parameter.Type()
		if held.Exists() && !held.IsValueType() && program.DeclaresType(strings.TrimSuffix(held.Name(), "?")) {
			owners = append(owners, parameter.Name())
		}
		if slices.Contains(keyTypes, strings.TrimSuffix(held.Name(), "?")) {
			keys = append(keys, parameter.Name())
		}
	}
	reads := body.expressionParts()
	if len(owners) == 0 || len(keys) == 0 {
		return false
	}

	return slices.ContainsFunc(reads, func(lookup Node) bool {
		root := lookup.LookupRootKeyedBy(keys)
		if !root.Exists() || !slices.Contains(owners, root.Name()) {
			return false
		}
		local := lookup.CapturedLocal()

		return local != "" && !body.IsResolverReturning(local) && root.IsOnlyReadThrough(reads)
	})
}

// EnviedParameter is the parameter the method envies: the one other object it loops the collection of or writes
// the members of, reaching into it more than into its own state; empty when it envies nothing. A type that may
// fill a contract, a method that builds anything, a parameter of the method's own type, and a loop handing each
// element to the work the type does itself are not envy.
//
// A read that is a criterion of a query over the type's own state is not a reach into the other object: it says
// which of the type's own rows are wanted.
func (n Node) EnviedParameter(program *Program) string {
	host := n.EnclosingType()
	if !host.Exists() || host.MayFillAContract() || n.constructs() {
		return ""
	}
	own := host.StateNames()
	var owned []string
	for _, parameter := range n.Parameters() {
		held := parameter.Type()
		name := strings.TrimSuffix(held.Name(), "?")
		if held.Exists() && !held.IsValueType() && name != host.Symbol() && program.DeclaresType(name) {
			owned = append(owned, parameter.Name())
		}
	}
	reaches := n.reachesNotCriteria(owned, own)
	ownReaches := n.ownStateReaches(own)
	if len(reaches) != 1 {
		return ""
	}
	var envied string
	for name, count := range reaches {
		if count <= ownReaches {
			return ""
		}
		envied = name
	}
	loops := n.LoopsOver(envied)
	mutates := n.WritesMemberOf(envied)
	iterates := len(loops) > 0 || n.QueriesCollectionOf(envied)
	if iterates && !mutates && len(loops) > 0 && !slices.ContainsFunc(loops, func(loop Node) bool { return !n.handsElementOn(loop, own, host) }) {
		return ""
	}
	if n.WalksRecursively(envied, loops) {
		return ""
	}
	if iterates || mutates {
		return envied
	}

	return ""
}

// reachesNotCriteria is how often the method reads a member of each of the names, leaving out the reads that are
// criteria of a query over the type's own state.
func (n Node) reachesNotCriteria(names, own []string) map[string]int {
	counted := map[string]int{}
	for name, reads := range n.MemberReadsOn(names) {
		if kept := slices.DeleteFunc(reads, func(read Node) bool { return read.IsCriterionOfOwnQuery(own) }); len(kept) > 0 {
			counted[name] = len(kept)
		}
	}

	return counted
}

// ownStateReaches is how often the method reads its own type's state.
func (n Node) ownStateReaches(own []string) int {
	reaches := 0
	for _, expression := range n.OutermostExpressions() {
		reaches += len(expression.OwnStateReferences(own))
	}

	return reaches
}

// WalksRecursively says whether the method walks the parameter by calling itself on a part of it: an interpreter
// over a tree of plain data, where each node's children are evaluated the same way.
//
// Such a walk cannot move onto the type it reads. The evaluation needs what the walker was given besides the node
// — a context, a bound value, the walk itself — and a node that evaluated itself would have to be handed all of
// it, which is the walker turned inside out. A stored expression is data, and the thing that reads it is a
// program.
func (n Node) WalksRecursively(parameter string, loops []Node) bool {
	own := n.Name()
	if own == "" || parameter == "" {
		return false
	}

	walked := []string{parameter}
	for _, loop := range loops {
		if loop.Name() != "" {
			walked = append(walked, loop.Name())
		}
	}
	walked = append(walked, n.partsBoundByAQueryOver(parameter)...)

	for _, expression := range n.OutermostExpressions() {
		for _, call := range expression.Flatten() {
			if !call.IsCall() || call.CalledName() != own {
				continue
			}

			if slices.ContainsFunc(call.Arguments(), func(argument Node) bool { return reachesAny(argument, walked) }) {
				return true
			}
		}
	}

	return false
}

// reachesAny says whether the expression reads one of the names, as the name itself or through what it holds: the
// node the walk was given, or an element of it the walk is looping over.
func reachesAny(expression Node, names []string) bool {
	for _, part := range append(expression.Flatten(), expression) {
		if part.Is("IdentifierName") && slices.Contains(names, part.Name()) {
			return true
		}
	}

	return false
}

// constructs says whether the code builds anything: `new` of any type, named or anonymous.
func (n Node) constructs() bool {
	return slices.ContainsFunc(n.expressionParts(), func(node Node) bool {
		return node.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression", "AnonymousObjectCreationExpression")
	})
}

// handsElementOn says whether the loop hands its element to the work the type does itself: to a collaborator it
// holds under its own name, `printer.Print(line)`, or to a method of its own, `Lookup(id)`. Either way the loop
// drives the type's own job one element at a time, and the collection it reads carries no behaviour that could
// take it over. Calling this method again is recursion, which WalksRecursively reads.
func (n Node) handsElementOn(loop Node, own []string, host Node) bool {
	return slices.ContainsFunc(loop.expressionParts(), func(call Node) bool {
		return call.IsCall() && call.CalledName() != n.Name() &&
			(heldUnderOwnName(call, own) || isTheTypesOwnWork(call, host)) &&
			slices.ContainsFunc(call.Arguments(), func(argument Node) bool { return argument.Is("IdentifierName") && argument.Name() == loop.Name() })
	})
}

// heldUnderOwnName says whether the call is made on a collaborator the type holds as its own state.
func heldUnderOwnName(call Node, own []string) bool {
	return call.At(0).Is("SimpleMemberAccessExpression") && slices.Contains(own, call.At(0).At(0).MemberName())
}

// isTheTypesOwnWork says whether the call is the type's own work: a method it declares, as the compiler resolved
// the call, or a call written with no receiver at all that the compiler could not bind.
//
// A method whose tree is judged without the package one of its calls names binds nothing else in that method
// either, since the element's type is then an error type and every call taking it fails to resolve. A rule may
// not report what the engine could not read, so an unbound call written as the type's own is taken as the type's
// own — which is what it nearly always is, a method beside it or a local function of its own.
func isTheTypesOwnWork(call Node, host Node) bool {
	if !call.Target().Exists() {
		return call.At(0).Is("IdentifierName", "GenericName")
	}

	return host.Symbol() != "" && call.Target().Type() == host.Symbol()
}

// IsCriterionOfOwnQuery says whether the read of another object's member stands inside a lambda that a query over
// the type's OWN state was handed: `rows.Where(row => row.Name == descriptor.Name)`.
//
// Such a read says WHICH of the type's own rows are wanted. It is a criterion, not a reach into the other
// object's data, and a method like that moved onto the object it reads would take the type's own collection with
// it, which is the envy turned around.
func (n Node) IsCriterionOfOwnQuery(own []string) bool {
	for held := n; held.Exists(); held = held.Parent() {
		if !held.Is("SimpleLambdaExpression", "ParenthesizedLambdaExpression") {
			continue
		}
		if queried := held.QueriedName(); queried != "" && slices.Contains(own, queried) {
			return true
		}
	}

	return false
}

// partsBoundByAQueryOver are the names the parts of the parameter are bound to by a query over them: the `part` of
// `expression.Parts.All(part => ...)`, which walks those parts as surely as a `foreach` over them does.
func (n Node) partsBoundByAQueryOver(parameter string) []string {
	var names []string
	for _, part := range n.expressionParts() {
		if !part.Is("SimpleLambdaExpression", "ParenthesizedLambdaExpression") {
			continue
		}
		if part.QueriedName() == parameter {
			names = append(names, part.LambdaNames()...)
		}
	}

	return names
}

// HoldsCoupledFields says whether the type holds one value as several of its own fields: two or more value fields
// assembled into one value together again and again or null-checked together, a proper subset of the fields; or a
// field mirroring what a sibling field already holds.
func (n Node) HoldsCoupledFields(program *Program) bool {
	if !n.IsTypeDeclaration() || n.Is("InterfaceDeclaration", "EnumDeclaration") {
		return false
	}
	state := n.StateTypes()
	var values []string
	for _, held := range state {
		name := strings.TrimSuffix(held.Type.Name(), "?")
		if held.Type.Exists() && (held.Type.IsValueType() || name == stringType || program.DeclaresRecord(name)) {
			values = append(values, held.Name)
		}
	}

	return len(state) >= 2 && (n.assemblesValuesTogether(values, len(state), program) || mirrorsASibling(state, program))
}

// assemblesValuesTogether says whether two or more of the values, a proper subset of the type's fields, are
// assembled into one value together in two places, or with at least half of them null-checked together.
func (n Node) assemblesValuesTogether(values []string, fieldCount int, program *Program) bool {
	tested := n.OwnMembersTestedForNull(values)
	occurrences := map[string]int{}
	for _, group := range n.assembledGroups(values, program) {
		if len(group) < 2 || len(group) >= fieldCount {
			continue
		}
		guarded := 0
		for _, name := range group {
			if slices.Contains(tested, name) {
				guarded++
			}
		}
		if guarded >= 2 && guarded*2 >= len(group) {
			return true
		}
		sorted := slices.Clone(group)
		sort.Strings(sorted)
		key := strings.Join(sorted, ",")
		if occurrences[key]++; occurrences[key] >= 2 {
			return true
		}
	}

	return false
}

// assembledGroups is every group of two or more of the type's own values handed, as they are, to one tuple,
// collection or type of the codebase's being built.
func (n Node) assembledGroups(own []string, program *Program) [][]string {
	var groups [][]string
	for _, built := range n.expressionParts() {
		var parts []Node
		switch {
		case built.Is("TupleExpression", "CollectionExpression"):
			for _, element := range built.All() {
				expressions := element.Expressions()
				if len(expressions) == 0 {
					continue
				}
				parts = append(parts, expressions[0])
			}
		case built.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression") && n.buildsAnotherOwnType(built, program):
			parts = built.Arguments()
		}
		var fields []string
		for _, part := range parts {
			if name := part.WithoutNullableUnwrap().MemberName(); slices.Contains(own, name) && !slices.Contains(fields, name) {
				fields = append(fields, name)
			}
		}
		if len(fields) >= 2 {
			groups = append(groups, fields)
		}
	}

	return groups
}

// buildsAnotherOwnType says whether the creation builds a type the codebase declares, other than this one.
func (n Node) buildsAnotherOwnType(creation Node, program *Program) bool {
	built := strings.TrimSuffix(creation.Type().Name(), "?")

	return built != n.Symbol() && program.DeclaresType(built)
}

// mirrorsASibling says whether a field mirrors a value a sibling field already holds: `WorkflowId` beside a
// `Workflow` whose type has an `Id` of the same type.
func mirrorsASibling(state []State, program *Program) bool {
	for _, object := range state {
		declaration, declared := program.TypeDeclared(strings.TrimSuffix(object.Type.Name(), "?"))
		if !declared {
			continue
		}
		for _, inner := range declaration.State {
			mirrored := object.Name + capitalized(inner.Name)
			at := slices.IndexFunc(state, func(held State) bool { return held.Name == mirrored })
			if at >= 0 && state[at].Type.Exists() && inner.Type != "" && strings.TrimSuffix(state[at].Type.Name(), "?") == strings.TrimSuffix(inner.Type, "?") {
				return true
			}
		}
	}

	return false
}

// capitalized is the name with its first letter upper-cased.
func capitalized(name string) string {
	if name == "" {
		return ""
	}
	runes := []rune(name)
	runes[0] = unicode.ToUpper(runes[0])

	return string(runes)
}

// ResultIsAssertedPresent says whether the caller asserts the call's result is there, forcing it with `!`,
// `?? throw`, or a null test that only throws, right there or on the local it keeps the result in.
func (n Node) ResultIsAssertedPresent() bool {
	result := n
	for _, around := range n.Ancestors() {
		if !around.Is("ParenthesizedExpression", "AwaitExpression") {
			break
		}
		result = around
	}
	if result.isAssertedAt() {
		return true
	}
	local := result.CapturedLocal()
	function := n.EnclosingFunction()
	if local == "" || !function.Exists() {
		return false
	}

	return slices.ContainsFunc(function.expressionParts(), func(read Node) bool {
		return read.Is("IdentifierName") && read.Name() == local && read.isAssertedAt()
	})
}

// isAssertedAt says whether the value the read yields is asserted present where it stands: the operand of `!`, the
// left of `?? throw`, or the subject of a null test in an `if` that only throws.
func (n Node) isAssertedAt() bool {
	parent := n.Parent()
	if parent.Is("SuppressNullableWarningExpression") || (parent.Is("CoalesceExpression") && parent.At(0).Node() == n.Node() && parent.At(1).WithoutParentheses().Is("ThrowExpression")) {
		return true
	}
	tests := (parent.Is("IsPatternExpression") && parent.At(0).Node() == n.Node()) ||
		(parent.Is("EqualsExpression") && slices.ContainsFunc(parent.All(), func(side Node) bool { return side.Is("NullLiteralExpression") }))

	return tests && parent.Parent().Is("IfStatement") && parent.Parent().IsGuardThatOnlyThrows()
}
