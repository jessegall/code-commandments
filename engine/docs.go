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

func (m Match) HasCeremonyDoc() bool {
	read := m.grammar().CeremonyDoc
	return read != nil && read(m)
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
	return m.file != nil && m.file.isTest()
}

func (m Match) IsTestReaching(declaration Match) bool {
	return m.IsTest() && !declaration.IsTest()
}

func (f *File) isTest() bool {
	if f.Test {
		return true
	}

	grammar := spokenIn(f.Language()).grammar

	return grammar != nil && grammar.TestFile != nil && grammar.TestFile(f.FromProject(), f.Path)
}
