package engine

func (m Match) IsConstClassEnum() bool {
	read := m.grammar().ConstClass
	return read != nil && read(m)
}
