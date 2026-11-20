package engine

import (
	"slices"
	"strings"
)

func IndexedArgument(kind, container, key string) func(Match) (Match, Match) {
	return func(read Match) (Match, Match) {
		if read.Kind() == kind {
			return read.Child(container), read.Child(key)
		}
		return Match{}, Match{}
	}
}

func (m Match) SwitchesOnFlag() bool {
	if read := m.grammar().FlagArgument; read != nil {
		return read(m)
	}
	if !m.Is(Function) || m.IsConstructor() || m.IsOverride() {
		return false
	}
	body := m.Body()
	if len(body) != 1 || !body[0].Is(Branch) || len(body[0].Body()) == 0 || len(body[0].Otherwise()) == 0 {
		return false
	}
	test := body[0].Condition()
	return test.Is(Identifier) && slices.ContainsFunc(m.Parameters(), func(parameter Match) bool {
		return parameter.Name() == test.Name() && parameter.isBooleanType()
	})
}

func (m Match) isBooleanType() bool {
	typed := m.Typed()
	return typed != nil && slices.Contains(m.grammar().BooleanTypes, typed.Text)
}

func (m Match) IsUnusedParameter() bool {
	if read := m.grammar().UnusedParameter; read != nil {
		return read(m)
	}
	if !m.Is(Parameter) || m.Name() == "" || strings.HasPrefix(m.Name(), "_") || m.isSelfReceiver() {
		return false
	}
	function := m.DeclaringFunction()
	if function.IsImplicit() || function.HasModifier("virtual") || function.HasModifier("abstract") ||
		m.grammar().OwnsSignature != nil && !m.grammar().OwnsSignature(function) {
		return false
	}
	return len(function.Body()) > 0 && len(function.Parameters()) > 1 && m.IsUnread()
}

func (m Match) ResolvesParameterFromParameter() bool {
	grammar := m.grammar()
	if grammar.ResolvedParameter != nil {
		return grammar.ResolvedParameter(m)
	}
	if grammar.ArgumentLookup == nil || !m.Is(Function) || m.IsConstructor() || m.IsOverride() {
		return false
	}
	parameters := map[string]Match{}
	for _, parameter := range m.Parameters() {
		parameters[parameter.Name()] = parameter
	}
	nodes := m.OwnDescendants()
	for _, lookup := range nodes {
		container, key := grammar.ArgumentLookup(lookup)
		declared := parameters[container.Name()]
		if !container.Is(Identifier) || !key.Is(Identifier) || container.Name() == key.Name() ||
			!declared.Exists() || !parameters[key.Name()].Exists() {
			continue
		}
		if !lookup.hasLocalTypeDeclaration() && !container.declaresType(container.ElementType()).Exists() {
			continue
		}
		if !slices.ContainsFunc(nodes, func(use Match) bool {
			return use.Is(Identifier) && use.Name() == container.Name() && use.node != container.node && !declared.isAbove(use)
		}) {
			return true
		}
	}
	return false
}

func (m Match) FillsParameterForCaller() bool {
	if read := m.grammar().FilledParameter; read != nil {
		return read(m)
	}
	if !m.Is(Function) || m.IsConstructor() || m.IsOverride() {
		return false
	}
	nodes := m.OwnDescendants()
	if slices.ContainsFunc(nodes, func(node Match) bool { return node.Is(Return) && len(node.Children()) > 0 }) {
		return false
	}
	for position, parameter := range m.Parameters() {
		for _, write := range nodes {
			if field := writtenParameterField(write, parameter); field != "" && callerReadsFilledField(m, position, field) {
				return true
			}
		}
	}
	return false
}

func writtenParameterField(write, parameter Match) string {
	left, right := write.assignmentSides()
	if right.Exists() && left.Is(MemberAccess) && left.Receiver().Is(Identifier) && left.Receiver().Name() == parameter.Name() {
		return left.MemberName()
	}
	return ""
}

func callerReadsFilledField(function Match, position int, field string) bool {
	for _, call := range function.Referrers() {
		if !call.Is(Call) || call.IsTestReaching(function) {
			continue
		}
		arguments := call.Arguments()
		if position >= len(arguments) || !arguments[position].Is(Identifier) {
			continue
		}
		name := arguments[position].Name()
		if slices.ContainsFunc(call.EnclosingFunction().OwnDescendants(), func(read Match) bool {
			return read.node.Span.Start >= call.node.Span.End && read.Is(MemberAccess) && read.MemberName() == field &&
				read.Receiver().Is(Identifier) && read.Receiver().Name() == name
		}) {
			return true
		}
	}
	return false
}

func (m Match) MemberName() string {
	if name := m.NameRead(); name != "" {
		return name
	}
	return m.Child("sel").Name()
}

func DerivedArgumentCalls(codebase *Codebase) []Match {
	var findings []Match
	for _, calls := range argumentCallers(codebase) {
		if len(calls) < 2 {
			continue
		}
		for position := range calls[0].Arguments() {
			if !slices.ContainsFunc(calls, func(call Match) bool { return !isDerivedArgument(call.Arguments(), position) }) {
				findings = append(findings, calls...)
				break
			}
		}
	}
	return findings
}

func isDerivedArgument(arguments []Match, position int) bool {
	if position >= len(arguments) || !arguments[position].Is(MemberAccess) {
		return false
	}
	receiver := arguments[position].Receiver()
	if !receiver.Is(Identifier) {
		return false
	}
	fields := map[string]bool{}
	for _, argument := range arguments {
		if argument.SameSyntax(receiver) {
			return true
		}
		if argument.Is(MemberAccess) && argument.Receiver().SameSyntax(receiver) {
			fields[argument.MemberName()] = true
		}
	}
	return len(fields) >= 3
}

func ComputedBooleanCalls(codebase *Codebase) []Match {
	var findings []Match
	for _, calls := range argumentCallers(codebase) {
		if len(calls) < 2 {
			continue
		}
		var positions []int
		for position := range calls[0].Arguments() {
			if calls[0].parameterAt(position).isBooleanType() {
				positions = append(positions, position)
			}
		}
		if len(positions) >= 2 && !slices.ContainsFunc(calls, func(call Match) bool {
			return !hasComputedBooleanArguments(call.Arguments(), positions)
		}) {
			findings = append(findings, calls...)
		}
	}
	return findings
}

func hasComputedBooleanArguments(arguments []Match, positions []int) bool {
	var receiver Match
	for _, position := range positions {
		if position >= len(arguments) {
			return false
		}
		argument := arguments[position]
		if !argument.Is(MemberAccess) && !argument.Is(Call) {
			return false
		}
		subject := argument.Receiver()
		if !subject.Is(Identifier) || receiver.Exists() && !receiver.SameSyntax(subject) {
			return false
		}
		receiver = subject
	}
	return true
}

func argumentCallers(codebase *Codebase) map[string][]Match {
	callers := map[string][]Match{}
	for _, call := range codebase.WhereCall().Get() {
		if target := call.TargetSymbol(); !call.IsTest() && target != "" && len(codebase.Declarations(target)) > 0 {
			callers[target] = append(callers[target], call)
		}
	}
	return callers
}
