package engine

import "strings"

// phpSpace is the whitespace PHP's trim() strips, so offsets read here land where the PHP tool's do.
const phpSpace = " \t\n\r\x00\x0b"

// Source is a file's text, asked for the offsets a rewrite is laid out by: where a line starts and ends,
// how it is indented, and how the file opens a block.
type Source string

// IndentAt is everything on pos's line before pos.
func (s Source) IndentAt(pos int) string {
	return string(s[s.LineStartAt(pos):pos])
}

// LineStartAt is the offset the line holding pos begins at.
func (s Source) LineStartAt(pos int) int {
	return strings.LastIndexByte(string(s[:pos]), '\n') + 1
}

// LineEndAt is the offset just past the line holding pos, after its break, or the end of the source.
func (s Source) LineEndAt(pos int) int {
	newline, found := s.After(pos, "\n")
	if !found {
		return len(s)
	}

	return newline + 1
}

// LineContentEndAt is the offset just past the last non-blank byte on pos's line.
func (s Source) LineContentEndAt(pos int) int {
	start := s.LineStartAt(pos)

	return start + len(strings.TrimRight(string(s[start:s.LineEndAt(pos)]), phpSpace))
}

// LineAt is the 1-based line pos is on.
func (s Source) LineAt(pos int) int {
	return strings.Count(string(s[:max(0, min(pos, len(s)))]), "\n") + 1
}

// LineIndentAt is the leading whitespace of pos's line, whatever else precedes pos on it.
func (s Source) LineIndentAt(pos int) string {
	prefix := s.IndentAt(pos)

	return prefix[:len(prefix)-len(strings.TrimLeft(prefix, phpSpace))]
}

// Before is the offset of needle's last occurrence before pos.
func (s Source) Before(pos int, needle string) (int, bool) {
	at := strings.LastIndex(string(s[:pos]), needle)

	return at, at >= 0
}

// After is the offset of needle's first occurrence at or after pos.
func (s Source) After(pos int, needle string) (int, bool) {
	at := strings.Index(string(s[pos:]), needle)
	if at < 0 {
		return -1, false
	}

	return pos + at, true
}

// SkipWhitespace is the offset of the first non-whitespace byte at or after pos, short of limit.
func (s Source) SkipWhitespace(pos, limit int) int {
	for pos < limit && strings.IndexByte(" \t\r\n", s[pos]) >= 0 {
		pos++
	}

	return pos
}

// BlockOpener is how a block opens in this file, read from the first brace at or after pos: " {" after
// the header, or the brace on a line of its own at indent.
func (s Source) BlockOpener(pos int, indent string) string {
	if s.BraceOnItsOwnLine(pos) {
		return "\n" + indent + "{"
	}

	return " {"
}

// BraceOnItsOwnLine says whether the first brace at or after pos begins its line.
func (s Source) BraceOnItsOwnLine(pos int) bool {
	brace, found := s.After(pos, "{")

	return found && s.StartsItsLine(brace)
}

// OwnLineIndent is pos's line's indentation when pos begins the line.
func (s Source) OwnLineIndent(pos int) (string, bool) {
	if !s.StartsItsLine(pos) {
		return "", false
	}

	return s.IndentAt(pos), true
}

// StartsItsLine says whether only whitespace stands between the line's start and pos.
func (s Source) StartsItsLine(pos int) bool {
	return strings.Trim(s.IndentAt(pos), phpSpace) == ""
}

// Reindent lays text whose first line sat at column out at base: base goes before every line, a
// continuation line loses up to column leading spaces, and a blank line is left empty.
func Reindent(text string, column int, base string) string {
	lines := strings.Split(text, "\n")
	out := []string{base + lines[0]}
	for _, line := range lines[1:] {
		if strings.Trim(line, phpSpace) == "" {
			out = append(out, "")

			continue
		}
		strip := 0
		for strip < column && strip < len(line) && line[strip] == ' ' {
			strip++
		}
		out = append(out, base+line[strip:])
	}

	return strings.Join(out, "\n")
}
