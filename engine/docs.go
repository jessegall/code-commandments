package engine

func (m Match) DocTags() []string {
	if read := m.grammar().DocTags; read != nil && m.node != nil {
		return read(m)
	}

	return nil
}

func (m Match) DocComments() []string {
	var texts []string
	for _, comment := range m.Comments() {
		if comment.Kind == "doc" {
			texts = append(texts, comment.Text)
		}
	}

	return texts
}

func (m Match) BodyHash() string {
	if read := m.grammar().BodyHash; read != nil && m.Is(Function) {
		return read(m)
	}

	return ""
}

func (m Match) NodeHash(normalize bool) string {
	if read := m.grammar().NodeHash; read != nil {
		return read(m, normalize)
	}

	return ""
}

func (m Match) Copies() int {
	hash := m.BodyHash()
	if hash == "" {
		return 0
	}

	return bodies(m.file.Codebase())[hash]
}

func bodies(c *Codebase) map[string]int {
	return Analysis(c, "bodies", func(c *Codebase) map[string]int {
		counts := map[string]int{}
		for _, function := range c.WhereFunction().Get() {
			if hash := function.BodyHash(); hash != "" {
				counts[hash]++
			}
		}

		return counts
	})
}

func (m Match) IsTest() bool {
	if m.file == nil {
		return false
	}

	if m.file.Test {
		return true
	}

	read := m.grammar().TestFile

	return read != nil && read(m.file.Judged(), m.file.Path)
}
