package engine

import (
	"bytes"
	"strings"
)

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
	before := string(s.Source[s.lineStart():s.Start])
	if strings.TrimSpace(before) != "" {
		return ""
	}

	return before
}

// lineStart is the offset the span's first line starts at.
func (s Span) lineStart() int {
	return bytes.LastIndexByte(s.Source[:s.Start], '\n') + 1
}
