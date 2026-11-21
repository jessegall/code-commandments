package engine

// DocTags are the names of the tags the declaration's doc comments carry, as its bridge read them.
func (m Match) DocTags() []string {
	var names []string
	for _, comment := range m.Comments() {
		for _, tag := range comment.Tags {
			names = append(names, tag.Name)
		}
	}

	return names
}

func (m Match) DocComments() []string {
	var texts []string
	for _, comment := range m.Comments() {
		if comment.Form == "doc" {
			texts = append(texts, comment.Text)
		}
	}

	return texts
}

func (m Match) NodeHash(normalize bool) string {
	if rules := m.grammar().Hashing; rules != nil {
		return SyntaxHash([]Match{m}, rules, normalize)
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
	return m.file != nil && m.file.Purpose() != Ordinary
}

func (m Match) IsTestFixture() bool {
	return m.file != nil && m.file.Purpose() == TestFixture
}

func (m Match) IsTestReaching(declaration Match) bool {
	return m.IsTest() && !declaration.IsTest()
}
