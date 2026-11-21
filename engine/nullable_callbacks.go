package engine

func (m Match) DefaultValue() Match {
	return Match{}
}

func (m Match) IsNullish() bool {
	return m.LiteralKind() == "null" || m.LiteralKind() == "undefined"
}
