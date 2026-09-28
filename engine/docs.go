package engine

import "strings"

// DocTags are the tags the declaration's documentation carries, each by its bare name, as its language writes
// them; none for a language that registered no reading.
func (m Match) DocTags() []string {
	if read := m.lists().DocTags; read != nil && m.node != nil {
		return read(m)
	}

	return nil
}

// DocComments are the texts of the doc comments attached to the node.
func (m Match) DocComments() []string {
	var texts []string
	for _, comment := range m.Comments() {
		if comment.Kind == "doc" {
			texts = append(texts, comment.Text)
		}
	}

	return texts
}

// AtTags are the tags a PHPDoc or JSDoc comment carries: each line that opens with `@` names one, its bare name
// the word after the `@`.
func AtTags(comment string) []string {
	var tags []string
	for _, line := range strings.Split(comment, "\n") {
		line = strings.TrimLeft(strings.TrimSpace(line), "/*")
		word, _, _ := strings.Cut(strings.TrimSpace(line), " ")
		if name, tagged := strings.CutPrefix(word, "@"); tagged && name != "" {
			tags = append(tags, strings.TrimRight(name, "{}"))
		}
	}

	return tags
}

// AtTagsOf reads the tags of a declaration's PHPDoc or JSDoc comments.
func AtTagsOf(declaration Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		tags = append(tags, AtTags(comment)...)
	}

	return tags
}

// BodyHash is the formatting-blind fingerprint of the function's body, as its language takes it; empty for a node
// without one.
func (m Match) BodyHash() string {
	if read := m.lists().BodyHash; read != nil && m.Is(Function) {
		return read(m)
	}

	return ""
}

// Copies is how many functions of the codebase have this one's body, itself among them; none for a function
// whose body its language cannot fingerprint.
func (m Match) Copies() int {
	hash := m.BodyHash()
	if hash == "" {
		return 0
	}

	return bodies(m.file.Codebase())[hash]
}

// bodies counts the functions of the codebase by their body's fingerprint.
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

// IsTest says whether the node is test code: in a file its bridge marks as a test's, such as one of a C# test
// project, or one its language's conventions name so, read from the folder the scan was pointed at.
func (m Match) IsTest() bool {
	if m.file == nil {
		return false
	}

	if m.file.Test {
		return true
	}

	read := m.lists().TestFile

	return read != nil && read(m.file.Judged())
}
