package engine

import "slices"

func (m Match) Thrown() Match {
	return Match{}
}

func (m Match) FailureLocation() Match {
	return m
}

func (m Match) Cause() Match {
	return Match{}
}

func (m Match) IsInert() bool {
	return false
}

func (m Match) IsEmptyCollection() bool {
	return false
}

func (m Match) ConstructedType() string {
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
	return Match{}
}

func (m Match) IsFailureSite() bool {
	return m.Is(Throw) || m.Is(Return) && m.Thrown().Exists()
}
