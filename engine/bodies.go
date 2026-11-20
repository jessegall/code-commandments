package engine

import "slices"

func (m Match) BodyHash() string {
	return m.hashBody(false)
}

func (m Match) BodyShape() string {
	return m.hashBody(true)
}

func (m Match) hashBody(normalize bool) string {
	rules := m.grammar().Hashing
	if rules == nil {
		return ""
	}
	body := m.copyBody()
	if len(body) == 0 {
		return ""
	}

	return SyntaxHash(body, rules, normalize)
}

func (m Match) BodyWeight() int {
	if rules := m.grammar().Hashing; rules != nil {
		return SyntaxWeight(m.copyBody(), rules)
	}

	return 0
}

func (m Match) copyBody() []Match {
	if read := m.grammar().CopyBody; read != nil {
		return read(m)
	}
	if !m.Is(Function) {
		return nil
	}

	return m.Body()
}

// CopyExclusions marks declarations that a language excludes from clone detection.
type CopyExclusions struct {
	Exact bool
	Near  bool
}

func (m Match) ExcludesExactCopy() bool {
	if read := m.grammar().CopyExclusions; read != nil {
		return read(m).Exact
	}
	return m.IsConstructor() || m.IsSoleStatement()
}

func (m Match) ExcludesNearCopy() bool {
	read := m.grammar().CopyExclusions

	return read != nil && read(m).Near
}

func (m Match) IsConstructor() bool {
	read := m.grammar().IsConstructor

	return read != nil && m.node != nil && read(m)
}

func (m Match) IsSoleStatement() bool {
	body := m.Body()

	return len(body) == 1 && (body[0].Is(Return) || body[0].Is(ExpressionStatement))
}

func (m Match) IsStub() bool {
	body := slices.DeleteFunc(m.Body(), Match.IsInert)

	return len(body) == 0 || len(body) == 1 && body[0].ThrowWithin().Exists()
}

func (m Match) IsLiteralLookup() bool {
	body := m.Body()
	if len(body) == 0 || slices.ContainsFunc(m.Descendants(), func(node Match) bool { return node.Is(Call) }) {
		return false
	}
	returned := false
	for _, node := range m.Descendants() {
		if !node.Is(Return) {
			continue
		}
		returned = true
		if values := node.Children(); len(values) > 0 && !values[0].IsConstant() {
			return false
		}
	}

	return returned
}
