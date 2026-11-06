package engine

import "bytes"

// Span is a half-open byte range [Start, End) into one file's source.
type Span struct {
	Path   string
	Source []byte
	Start  int
	End    int
}

func (s Span) Text() string {
	return string(s.Source[s.Start:s.End])
}

func (s Span) Contains(other Span) bool {
	return s.Path == other.Path &&
		s.Start <= other.Start &&
		other.End <= s.End &&
		(s.Start != other.Start || s.End != other.End)
}

func (s Span) Line() int {
	return bytes.Count(s.Source[:s.Start], []byte("\n")) + 1
}

func (s Span) Column() int {
	return s.Start - s.lineStart()
}

func (s Span) LineIndent() string {
	indent, _ := Source(s.Source).OwnLineIndent(s.Start)

	return indent
}

func (s Span) Reindent(base string) string {
	return Reindent(s.Text(), len(Source(s.Source).LineIndentAt(s.Start)), base)
}

func (s Span) lineStart() int {
	return bytes.LastIndexByte(s.Source[:s.Start], '\n') + 1
}

func (s Span) Lines() string {
	end := len(s.Source)
	if next := bytes.IndexByte(s.Source[s.End:], '\n'); next >= 0 {
		end = s.End + next + 1
	}

	return string(s.Source[s.lineStart():end])
}
