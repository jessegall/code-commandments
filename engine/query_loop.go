package engine

func (m Match) ExecutesQueryInLoop() bool {
	read := m.grammar().ExecutesQuery
	if !m.Is(Call) || read == nil || m.IsTest() {
		return false
	}
	for parent := m.Parent(); parent.Exists() && !parent.Is(Function); parent = parent.Parent() {
		if parent.Is(Loop) {
			return read(m)
		}
	}
	return false
}
