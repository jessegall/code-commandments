package engine

import "bytes"

// Span is a half-open byte range [Start, End) into one file's source.
type Span struct {
	Path   string
	Source []byte
	Start  int
	End    int
}

// Text is the span's own slice of the source.
func (s Span) Text() string {
	return string(s.Source[s.Start:s.End])
}

// Contains says whether the span strictly holds other: same file, other's range inside it, not the same range.
func (s Span) Contains(other Span) bool {
	return s.Path == other.Path &&
		s.Start <= other.Start &&
		other.End <= s.End &&
		(s.Start != other.Start || s.End != other.End)
}

// Line is the 1-based line the span starts on.
func (s Span) Line() int {
	return bytes.Count(s.Source[:s.Start], []byte("\n")) + 1
}

// Column is the byte width of what precedes the span on its first line.
func (s Span) Column() int {
	return s.Start - s.lineStart()
}

// LineIndent is the indentation of the line the span starts on, or "" when code precedes it there.
func (s Span) LineIndent() string {
	indent, _ := Source(s.Source).OwnLineIndent(s.Start)

	return indent
}

// Reindent is the span's text laid out at base: the indentation of the line it begins on comes off
// every continuation line, so a span starting mid-line shifts with its block instead of flattening.
func (s Span) Reindent(base string) string {
	return Reindent(s.Text(), len(Source(s.Source).LineIndentAt(s.Start)), base)
}

// lineStart is the offset the span's first line starts at.
func (s Span) lineStart() int {
	return bytes.LastIndexByte(s.Source[:s.Start], '\n') + 1
}

// Lines is every line the span touches, whole, the last line's break kept.
func (s Span) Lines() string {
	end := len(s.Source)
	if next := bytes.IndexByte(s.Source[s.End:], '\n'); next >= 0 {
		end = s.End + next + 1
	}

	return string(s.Source[s.lineStart():end])
}
