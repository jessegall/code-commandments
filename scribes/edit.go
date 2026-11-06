// Package scribes is the rewrite machinery repent runs: edits drafted against a file's source, the files they produce, and the diff that previews them.
package scribes

import "slices"

// Edit replaces the half-open byte range [Start, End) of a source with Text; a pure insertion has Start == End.
type Edit struct {
	Start int
	End   int
	Text  string
}

func InsertAt(at int, text string) Edit {
	return Edit{Start: at, End: at, Text: text}
}

func (e Edit) AppliedTo(source string) string {
	return source[:e.Start] + e.Text + source[e.End:]
}

func lastFirst(a, b Edit) int {
	if a.Start != b.Start {
		return b.Start - a.Start
	}

	return b.End - a.End
}

func ApplyEdits(source string, edits []Edit) string {
	ordered := slices.Clone(edits)
	slices.SortStableFunc(ordered, lastFirst)
	for _, edit := range ordered {
		source = edit.AppliedTo(source)
	}

	return source
}
