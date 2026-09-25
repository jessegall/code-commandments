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
// element to one of the method's own collaborators are not envy.
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
	reaches := n.MemberReachesOn(owned)
	ownReaches := 0
	for _, expression := range n.OutermostExpressions() {
		ownReaches += len(expression.OwnStateReferences(own))
	}
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
	if iterates && !mutates && len(loops) > 0 && !slices.ContainsFunc(loops, func(loop Node) bool { return !n.handsElementOn(loop, own) }) {
		return ""
	}
	if iterates || mutates {
		return envied
	}

	return ""
}

// constructs says whether the code builds anything: `new` of any type, named or anonymous.
func (n Node) constructs() bool {
	return slices.ContainsFunc(n.expressionParts(), func(node Node) bool {
		return node.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression", "AnonymousObjectCreationExpression")
	})
}

// handsElementOn says whether the loop hands its element to one of the collaborators the type holds under its own
// names, `printer.Print(line)`: the orchestrator doing its own job. Calling this method again is recursion.
func (n Node) handsElementOn(loop Node, own []string) bool {
	return slices.ContainsFunc(loop.expressionParts(), func(call Node) bool {
		return call.IsCall() && call.At(0).Is("SimpleMemberAccessExpression") &&
			slices.Contains(own, call.At(0).At(0).MemberName()) && call.CalledName() != n.Name() &&
			slices.ContainsFunc(call.Arguments(), func(argument Node) bool { return argument.Is("IdentifierName") && argument.Name() == loop.Name() })
	})
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
				if expressions := element.Expressions(); len(expressions) > 0 {
					parts = append(parts, expressions[0])
				}
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
