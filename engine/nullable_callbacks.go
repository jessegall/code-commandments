package engine

func (m Match) DefaultValue() Match {
	if read := m.grammar().DefaultValue; read != nil {
		return read(m)
	}
	return Match{}
}

func (m Match) IsNullish() bool {
	return m.LiteralKind() == "null" || m.LiteralKind() == "undefined"
}
