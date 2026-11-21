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
		if comment.Form == "doc" {
			texts = append(texts, comment.Text)
		}
	}

	return texts
}

// StacksDocComments reports a declaration with several doc comments above it in one run: another kind of comment ends
// the run, and so does the file's header, a first comment a blank line keeps apart. A doc comment inside a function
// body is an inline hint, not a declaration's doc.
func (m Match) StacksDocComments() bool {
	if m.node == nil || (!m.Is(Function) && m.EnclosingFunction().Exists()) {
		return false
	}
	source, err := m.file.Source()
	if err != nil {
		return false
	}
	var above []int
	comments := m.Comments()
	for index, comment := range comments {
		if !comment.Trailing && comment.Span.End <= m.node.Span.Start {
			above = append(above, index)
		}
	}
	code := m.node.Span.Start
	for _, statement := range m.Root().Children() {
		code = min(code, statement.node.Span.Start)
	}
	run, end := 0, m.node.Span.Start
	for at := len(above) - 1; at >= 0; at-- {
		comment := comments[above[at]]
		header := comment.Span.End <= code && HasBlankLine(string(source[comment.Span.End:end]))
		if comment.Form != "doc" || header {
			break
		}
		run, end = run+1, comment.Span.Start
	}

	return run > 1
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
	return m.file != nil && m.file.Purpose() != Ordinary
}

func (m Match) IsTestFixture() bool {
	return m.file != nil && m.file.Purpose() == TestFixture
}

func (m Match) IsTestReaching(declaration Match) bool {
	return m.IsTest() && !declaration.IsTest()
}


