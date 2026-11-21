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
