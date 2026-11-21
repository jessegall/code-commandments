package engine


func fieldOnSelf(node Match) Match {
	for read := node; read.Exists(); read = read.Receiver() {
		if read.Is(MemberAccess) && read.Receiver().isSelfReceiver() {
			return read
		}
	}
	return Match{}
}

func (m Match) isSelfReceiver() bool {
	if m.Is(SelfReference) {
		return true
	}
	read := m.grammar().SelfReceiver
	return read != nil && read(m)
}

