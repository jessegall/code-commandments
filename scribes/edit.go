// Package scribes is the rewrite machinery repent runs: edits drafted against a file's source, the
// files they produce, and the diff that previews them. It knows no language; a scribe of each engine
// drafts through it.
package scribes

// Edit replaces the half-open byte range [Start, End) of a source with Text; a pure insertion has
// Start == End.
type Edit struct {
	Start int
	End   int
	Text  string
}

// InsertAt is a pure insertion at an offset, consuming nothing.
func InsertAt(at int, text string) Edit {
	return Edit{Start: at, End: at, Text: text}
}

// AppliedTo is the source with the edit's range replaced by its text.
func (e Edit) AppliedTo(source string) string {
	return source[:e.Start] + e.Text + source[e.End:]
}

// lastFirst orders edits for application: right to left, so an earlier edit's offsets stay valid, and
// at a shared start the wider edit first, so an insertion composes with a replacement abutting it.
func lastFirst(a, b Edit) int {
	if a.Start != b.Start {
		return b.Start - a.Start
	}

	return b.End - a.End
}
