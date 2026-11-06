package csharp

import (
	"slices"
	"sort"
	"strings"
	"unicode"

	"github.com/jessegall/code-commandments/engine"
)

var keyTypes = []string{"global::System.String", "global::System.Int32", "global::System.Int64", "global::System.Guid"}

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
	if iterates && !mutates && len(loops) > 0 && n.everyLoopPassesItsElementOn(loops) {
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

func (n Node) reachesNotCriteria(names, own []string) map[string]int {
	counted := map[string]int{}
	for name, reads := range n.MemberReadsOn(names) {
		if kept := slices.DeleteFunc(reads, func(read Node) bool { return read.IsCriterionOfOwnQuery(own) }); len(kept) > 0 {
			counted[name] = len(kept)
		}
	}

	return counted
}

func (n Node) ownStateReaches(own []string) int {
	reaches := 0
	for _, expression := range n.OutermostExpressions() {
		reaches += len(expression.OwnStateReferences(own))
	}

	return reaches
}

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

func reachesAny(expression Node, names []string) bool {
	for _, part := range append(expression.Flatten(), expression) {
		if part.Is("IdentifierName") && slices.Contains(names, part.Name()) {
			return true
		}
	}

	return false
}

func (n Node) constructs() bool {
	return slices.ContainsFunc(n.expressionParts(), func(node Node) bool {
		return node.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression", "AnonymousObjectCreationExpression")
	})
}

func (n Node) everyLoopPassesItsElementOn(loops []Node) bool {
	return !slices.ContainsFunc(loops, func(loop Node) bool { return !engine.PassesItsElementOn(loop.Match) })
}

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

func (n Node) buildsAnotherOwnType(creation Node, program *Program) bool {
	built := strings.TrimSuffix(creation.Type().Name(), "?")

	return built != n.Symbol() && program.DeclaresType(built)
}

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

func capitalized(name string) string {
	if name == "" {
		return ""
	}
	runes := []rune(name)
	runes[0] = unicode.ToUpper(runes[0])

	return string(runes)
}

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

func (n Node) isAssertedAt() bool {
	parent := n.Parent()
	if parent.Is("SuppressNullableWarningExpression") || (parent.Is("CoalesceExpression") && parent.At(0).Node() == n.Node() && parent.At(1).WithoutParentheses().Is("ThrowExpression")) {
		return true
	}
	tests := (parent.Is("IsPatternExpression") && parent.At(0).Node() == n.Node()) ||
		(parent.Is("EqualsExpression") && slices.ContainsFunc(parent.All(), func(side Node) bool { return side.Is("NullLiteralExpression") }))

	return tests && parent.Parent().Is("IfStatement") && parent.Parent().IsGuardThatOnlyThrows()
}
