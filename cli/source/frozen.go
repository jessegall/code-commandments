package source

import (
	"regexp"
	"strings"
)

const (
	// FileMarker is the whole-file freeze stamp `commandments freeze` writes.
	FileMarker = "@code-commandments-frozen"

	// GeneratedMarker is the stamp a generator puts on a file it regenerates.
	GeneratedMarker = "@code-commandments-generated"

	// frozenAttribute is the attribute form of the freeze, `#[Frozen]`.
	frozenAttribute = "Frozen"
)

var frozenTag = regexp.MustCompile(`(?i)@frozen\b`)

// IsFrozenFile says whether the file is frozen: scanned, but never a target. A marker freezes it only where
// it is declared, as the attribute or in a comment of the file's own language, and a file its language's
// toolchain generates is frozen too.
func IsFrozenFile(path, source string) bool {
	language := OfFile(path)

	return IsFrozen(source, language) || language.IsGenerated(path, source)
}

// IsFrozen says whether the source declares a freeze in a comment or, in PHP, as `#[Frozen]`.
func IsFrozen(source string, language Language) bool {
	if !mentionsAMarker(source) {
		return false
	}

	if language != PHP {
		for _, line := range strings.Split(source, "\n") {
			if language.IsCommentLine(line) && declaresFreeze(line) {
				return true
			}
		}

		return false
	}

	for _, token := range phpTokens(source) {
		if token.comment && declaresFreeze(token.text) {
			return true
		}

		if token.attribute == frozenAttribute {
			return true
		}
	}

	return false
}

func declaresFreeze(comment string) bool {
	return strings.Contains(comment, FileMarker) || strings.Contains(comment, GeneratedMarker) || frozenTag.MatchString(comment)
}

func mentionsAMarker(source string) bool {
	return strings.Contains(source, FileMarker) || strings.Contains(source, GeneratedMarker) ||
		strings.Contains(source, frozenAttribute) || frozenTag.MatchString(source)
}

// phpToken is what a freeze is read from: a comment's text, or the name an attribute opens with.
type phpToken struct {
	comment   bool
	text      string
	attribute string
}

// phpTokens lexes PHP source only as far as a freeze needs: it skips inline HTML, strings, heredocs and
// nowdocs, and yields each comment and the first name of each attribute.
func phpTokens(source string) []phpToken {
	var tokens []phpToken
	i, n := 0, len(source)

	for i < n {
		open := indexOpenTag(source, i)
		if open < 0 {
			return tokens
		}

		i = open

		for i < n {
			switch c := source[i]; {
			case strings.HasPrefix(source[i:], "?>"):
				i += 2

				goto html
			case strings.HasPrefix(source[i:], "#["):
				i += 2
				tokens = append(tokens, phpToken{attribute: attributeName(source, &i)})
			case c == '#' || strings.HasPrefix(source[i:], "//"):
				end := lineCommentEnd(source, i)
				tokens = append(tokens, phpToken{comment: true, text: source[i:end]})
				i = end
			case strings.HasPrefix(source[i:], "/*"):
				end := strings.Index(source[i+2:], "*/")
				if end < 0 {
					end = n
				} else {
					end = i + 2 + end + 2
				}

				tokens = append(tokens, phpToken{comment: true, text: source[i:end]})
				i = end
			case c == '\'' || c == '"' || c == '`':
				i = quotedEnd(source, i)
			case strings.HasPrefix(source[i:], "<<<"):
				i = heredocEnd(source, i)
			default:
				i++
			}
		}
	html:
	}

	return tokens
}

// indexOpenTag is where PHP code starts after an opening tag at or after from, or -1.
func indexOpenTag(source string, from int) int {
	at := strings.Index(source[from:], "<?")
	if at < 0 {
		return -1
	}

	at += from

	switch {
	case strings.HasPrefix(strings.ToLower(source[at:]), "<?php") && (at+5 == len(source) || isSpace(source[at+5])):
		return at + 6
	case strings.HasPrefix(source[at:], "<?="):
		return at + 3
	default:
		return at + 2
	}
}

// lineCommentEnd is where a `//` or `#` comment ends: the line break, or a closing tag, which ends it too.
func lineCommentEnd(source string, from int) int {
	for i := from; i < len(source); i++ {
		if source[i] == '\n' || strings.HasPrefix(source[i:], "?>") {
			return i
		}

		if source[i] == '\r' {
			return i
		}
	}

	return len(source)
}

// attributeName reads the name an attribute opens with, past any whitespace and comments, and leaves i
// after it. A qualified name is kept whole, so `#[\Frozen]` never reads as `Frozen`.
func attributeName(source string, i *int) string {
	for *i < len(source) && isSpace(source[*i]) {
		*i++
	}

	start := *i

	for *i < len(source) && (isNameByte(source[*i]) || source[*i] == '\\') {
		*i++
	}

	return source[start:*i]
}

// quotedEnd is the index after the string opened at from, honouring backslash escapes.
func quotedEnd(source string, from int) int {
	quote := source[from]

	for i := from + 1; i < len(source); i++ {
		switch source[i] {
		case '\\':
			i++
		case quote:
			return i + 1
		}
	}

	return len(source)
}

// heredocEnd is the index after the heredoc or nowdoc opened at from: past the line that closes it with
// its label.
func heredocEnd(source string, from int) int {
	i := from + 3

	for i < len(source) && (source[i] == ' ' || source[i] == '\t') {
		i++
	}

	quoted := i < len(source) && (source[i] == '\'' || source[i] == '"')
	if quoted {
		i++
	}

	start := i

	for i < len(source) && isNameByte(source[i]) {
		i++
	}

	label := source[start:i]
	if label == "" {
		return from + 3
	}

	if quoted {
		i++
	}

	for line := strings.IndexByte(source[i:], '\n'); line >= 0; line = strings.IndexByte(source[i:], '\n') {
		i += line + 1
		body := strings.TrimLeft(source[i:], " \t")

		if strings.HasPrefix(body, label) && (len(body) == len(label) || !isNameByte(body[len(label)])) {
			return len(source) - len(body) + len(label)
		}
	}

	return len(source)
}

func isSpace(c byte) bool {
	return c == ' ' || c == '\t' || c == '\n' || c == '\r' || c == '\f' || c == '\v'
}

func isNameByte(c byte) bool {
	return c == '_' || c >= '0' && c <= '9' || c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z' || c >= 0x80
}
