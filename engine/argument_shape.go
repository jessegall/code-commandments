package engine

func IndexedArgument(kind, container, key string) func(Match) (Match, Match) {
	return func(read Match) (Match, Match) {
		if read.Kind() == kind {
			return read.Child(container), read.Child(key)
		}
		return Match{}, Match{}
	}
}

func (m Match) MemberName() string {
	if name := m.NameRead(); name != "" {
		return name
	}
	return m.Child("sel").Name()
}
