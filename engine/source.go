package engine

import "strings"

const phpSpace = " \t\n\r\x00\x0b"

// Source is a file's text, asked for the offsets a rewrite is laid out by: where a line starts and ends, how it is indented, and how the file opens a block.
type Source string

func (s Source) IndentAt(pos int) string {
	return string(s[s.LineStartAt(pos):pos])
}

func (s Source) LineStartAt(pos int) int {
	return strings.LastIndexByte(string(s[:pos]), '\n') + 1
}

func (s Source) LineEndAt(pos int) int {
	newline, found := s.After(pos, "\n")
	if !found {
		return len(s)
	}

	return newline + 1
}

func (s Source) LineContentEndAt(pos int) int {
	start := s.LineStartAt(pos)

	return start + len(strings.TrimRight(string(s[start:s.LineEndAt(pos)]), phpSpace))
}

func (s Source) LineAt(pos int) int {
	return strings.Count(string(s[:max(0, min(pos, len(s)))]), "\n") + 1
}

func (s Source) LineIndentAt(pos int) string {
	prefix := s.IndentAt(pos)

	return prefix[:len(prefix)-len(strings.TrimLeft(prefix, phpSpace))]
}

func (s Source) Before(pos int, needle string) (int, bool) {
	at := strings.LastIndex(string(s[:pos]), needle)

	return at, at >= 0
}

func (s Source) After(pos int, needle string) (int, bool) {
	at := strings.Index(string(s[pos:]), needle)
	if at < 0 {
		return -1, false
	}

	return pos + at, true
}

func (s Source) SkipWhitespace(pos, limit int) int {
	for pos < limit && strings.IndexByte(" \t\r\n", s[pos]) >= 0 {
		pos++
	}

	return pos
}

func (s Source) BlockOpener(pos int, indent string) string {
	if s.BraceOnItsOwnLine(pos) {
		return "\n" + indent + "{"
	}

	return " {"
}

func (s Source) BraceOnItsOwnLine(pos int) bool {
	brace, found := s.After(pos, "{")

	return found && s.StartsItsLine(brace)
}

func (s Source) OwnLineIndent(pos int) (string, bool) {
	if !s.StartsItsLine(pos) {
		return "", false
	}

	return s.IndentAt(pos), true
}

func (s Source) StartsItsLine(pos int) bool {
	return strings.Trim(s.IndentAt(pos), phpSpace) == ""
}

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
