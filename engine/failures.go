package engine

import "slices"

func (m Match) Thrown() Match {
	if read := m.grammar().Thrown; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

func (m Match) FailureLocation() Match {
	if read := m.grammar().FailureLocation; read != nil {
		return read(m)
	}
	return m
}

func (m Match) Cause() Match {
	if read := m.grammar().Cause; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

func (m Match) IsInert() bool {
	read := m.grammar().Inert

	return read != nil && m.node != nil && read(m)
}

func (m Match) IsEmptyCollection() bool {
	read := m.grammar().EmptyCollection

	return read != nil && m.node != nil && read(m)
}

func (m Match) ConstructedType() string {
	if read := m.grammar().ConstructedType; read != nil && m.node != nil {
		return read(m)
	}

	return ""
}

func (m Match) IsAbsence() bool {
	text := ""
	if value := m.Value(); value != nil {
		text, _ = value.Text()
	}
	switch m.LiteralKind() {
	case "null", "undefined":
		return true
	case "bool":
		if value := m.Value(); value != nil {
			boolean, known := value.Bool()
			return known && !boolean || text == "false"
		}
		return false
	case "string":
		return text == ""
	}

	return m.IsEmptyCollection()
}

func (m Match) ThrowWithin() Match {
	if m.Is(Throw) {
		return m
	}
	if inside := m.Children(); m.Is(ExpressionStatement) && len(inside) == 1 && inside[0].Is(Throw) {
		return inside[0]
	}

	return Match{}
}

func (m Match) IsConstruction() bool {
	return m.Is(Construction) || m.Constructed().Exists()
}

func (m Match) EnclosingCatch() Match {
	for _, above := range m.Ancestors() {
		if above.Is(Function) {
			return Match{}
		}
		if above.Is(Catch) {
			return above
		}
		if above.handledError().Exists() && slices.ContainsFunc(above.Body(), func(statement Match) bool {
			return statement.node == m.node || statement.isAbove(m)
		}) {
			return above
		}
	}

	return Match{}
}

func (m Match) HandlesFailure() bool {
	return m.Is(Catch) || m.handledError().Exists()
}

func (m Match) handledError() Match {
	if read := m.grammar().ErrorHandler; read != nil && m.Exists() {
		return read(m)
	}

	return Match{}
}

func (m Match) IsFailureSite() bool {
	return m.Is(Throw) || m.Is(Return) && m.Thrown().Exists()
}

func IsSwallowedCatch(m Match) bool {
	broad := m.grammar().BroadCatch
	if !m.HandlesFailure() || broad != nil && !broad(m) {
		return false
	}
	var body []Match
	for _, statement := range m.Body() {
		if !statement.IsInert() {
			body = append(body, statement)
		}
	}
	if len(body) == 0 {
		return true
	}
	if len(body) > 1 || !body[0].Is(BailOut) || body[0].ThrowWithin().Exists() {
		return false
	}
	if read := m.grammar().SwallowedExit; read != nil && !read(body[0]) {
		return false
	}
	returned := body[0].Children()
	if !m.Is(Catch) && body[0].Is(Return) {
		return len(returned) > 0 && !slices.ContainsFunc(returned, func(value Match) bool { return !value.IsAbsence() })
	}

	return len(returned) == 0 || returned[0].IsAbsence()
}

func IsUnchangedRethrow(m Match) bool {
	body := m.Body()
	if !m.Is(Catch) || len(body) != 1 || !m.isTheLastCatch() {
		return false
	}
	throw := body[0].ThrowWithin()
	thrown := throw.Thrown()
	if !throw.Exists() || throw.Cause().Exists() {
		return false
	}

	return !thrown.Exists() || thrown.Is(Identifier) && thrown.Name() != "" && thrown.Name() == m.DeclaredName()
}

func (m Match) isTheLastCatch() bool {
	clauses := slices.DeleteFunc(m.Parent().Children(), func(sibling Match) bool { return !sibling.Is(Catch) })

	return len(clauses) > 0 && clauses[len(clauses)-1].node == m.node
}

func IsWrappingWithoutCause(m Match) bool {
	thrown := m.Thrown()
	catch := m.EnclosingCatch()
	if !m.IsFailureSite() || !thrown.Exists() || !catch.Exists() || m.Cause().Exists() {
		return false
	}
	if m.Is(Return) {
		return true
	}
	if !thrown.IsConstruction() && !thrown.Is(Call) {
		return false
	}
	caught := catch.DeclaredName()
	if caught == "" {
		return true
	}
	if m.grammar().Cause != nil {
		return true
	}

	return !slices.ContainsFunc(thrown.Descendants(), func(reference Match) bool {
		return reference.Is(Identifier) && reference.Name() == caught && reference.Parent().Receiver().node != reference.node
	})
}

func IsGenericThrow(m Match) bool {
	thrown := m.Thrown()
	grammar := m.grammar()
	return m.Is(Throw) && thrown.IsConstruction() && slices.Contains(grammar.GenericFailures, thrown.ConstructedType()) &&
		(!grammar.GenericMessage || HasMessageAtThrow(m))
}

func HasMessageAtThrow(m Match) bool {
	thrown := m.Thrown()
	if !m.IsFailureSite() || !thrown.Exists() {
		return false
	}
	if m.Is(Return) {
		if m.Cause().Exists() || m.EnclosingCatch().Exists() {
			return false
		}
	} else if !thrown.IsConstruction() {
		return false
	}
	if reads := m.grammar().TakesFailureMessage; reads != nil && !reads(thrown) {
		return false
	}
	arguments := thrown.Arguments()

	return len(arguments) > 0 && slices.Contains([]string{"string", "interpolated"}, arguments[0].LiteralKind())
}
