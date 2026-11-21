package engine

func (m Match) MemberName() string {
	if name := m.NameRead(); name != "" {
		return name
	}
	return m.Child("sel").Name()
}
