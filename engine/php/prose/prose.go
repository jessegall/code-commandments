// Package prose reads the words a comment or a name is written in: the words themselves, stemmed, and the phrasings
// the documentation rules look for. PHP's own patterns are PCRE with lookarounds; each is ported here with its
// lookarounds checked by hand, and held to PHP's answers.
package prose

import (
	"regexp"
	"slices"
	"strings"
)

// filler are the words that carry grammar, not meaning.
var filler = []string{
	"the", "a", "an", "and", "or", "of", "to", "in", "on", "at", "by", "for", "from", "with",
	"into", "onto", "this", "that", "these", "those", "it", "its", "we", "us", "our", "you",
	"your", "they", "them", "their", "he", "she", "his", "her", "is", "are", "was", "were",
	"be", "been", "being", "am", "do", "does", "did", "has", "have", "had", "will", "as",
	"here", "there", "then", "so", "up", "out", "per", "via", "each",
	"over", "off", "about", "upon", "across", "through", "between", "during", "within", "against",
	"back", "than", "but",
}

var (
	camelHump  = regexp.MustCompile(`([a-z0-9])([A-Z])`)
	nonLetters = regexp.MustCompile(`[^A-Za-z]+`)
)

// Words is the meaningful words of a text, camel case split, lower-cased and stemmed, single letters and filler
// dropped.
func Words(text string) []string {
	var words []string
	for _, token := range nonLetters.Split(camelHump.ReplaceAllString(text, "$1 $2"), -1) {
		word := strings.ToLower(token)
		if len(word) < 2 || slices.Contains(filler, word) {
			continue
		}
		words = append(words, Stem(word))
	}

	return words
}

// Stem strips one common ending and a final e, so the forms of a word compare equal.
func Stem(word string) string {
	for _, ending := range []string{"ing", "ies", "ed", "es", "s"} {
		if !strings.HasSuffix(word, ending) || len(word)-len(ending) < 3 {
			continue
		}
		if ending == "ies" {
			word = word[:len(word)-3] + "y"
		} else {
			word = word[:len(word)-len(ending)]
		}

		break
	}
	if strings.HasSuffix(word, "e") && len(word) >= 4 {
		return word[:len(word)-1]
	}

	return word
}

// Paragraphs counts the runs of lines, separated by blank ones, that open with a prose line.
func Paragraphs(lines []string, isProse func(string) bool) int {
	paragraphs, inParagraph := 0, false
	for _, line := range lines {
		if Trim(line) == "" {
			inParagraph = false

			continue
		}
		if isProse(line) && !inParagraph {
			paragraphs++
			inParagraph = true
		}
	}

	return paragraphs
}

// Trim trims what PHP's trim trims: spaces, tabs, line ends, NUL and vertical tabs.
func Trim(text string) string {
	return strings.Trim(text, " \t\n\r\x00\x0B")
}

// Lines splits a text at every line end PCRE's \R matches without the u flag.
func Lines(text string) []string {
	var lines []string
	start := 0
	for at := 0; at < len(text); at++ {
		switch text[at] {
		case '\r':
			lines = append(lines, text[start:at])
			if at+1 < len(text) && text[at+1] == '\n' {
				at++
			}
			start = at + 1
		case '\n', '\x0B', '\f', '\x85':
			lines = append(lines, text[start:at])
			start = at + 1
		}
	}

	return append(lines, text[start:])
}

// isWord says whether a byte is a word character as PCRE reads one without the u flag.
func isWord(b byte) bool {
	return b == '_' || b >= '0' && b <= '9' || b >= 'a' && b <= 'z' || b >= 'A' && b <= 'Z'
}

// boundary says whether a word boundary falls at the offset.
func boundary(text string, at int) bool {
	before := at > 0 && isWord(text[at-1])
	after := at < len(text) && isWord(text[at])

	return before != after
}

// endsWithWord says whether the text before the offset ends with the word and a space, the word opening at a
// boundary, in any case: PCRE's (?<!\bword ).
func endsWithWord(text string, at int, word string) bool {
	suffix := word + " "
	start := at - len(suffix)

	return start >= 0 && strings.EqualFold(text[start:at], suffix) && boundary(text, start)
}

func precededByAny(text string, at int, words []string) bool {
	return slices.ContainsFunc(words, func(word string) bool { return endsWithWord(text, at, word) })
}
