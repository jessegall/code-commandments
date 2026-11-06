// Package prose reduces English to the words that carry meaning, and reads what a sentence does: narrate the code's past, or defend it against a reading nobody made.
package prose

import (
	"regexp"
	"slices"
	"strings"
	"unicode"
	"unicode/utf8"
)

var history = []phrase{
	{pattern: words(`formerly`), notAfter: endsWith(`a|an|the`)},
	{pattern: words(`refactored|renamed from|ported from|is retired`)},
	{pattern: words(`was extracted`), notBefore: regexp.MustCompile(`^\s+from`)},
	{pattern: words(`used to (?:be|live|have|hold|contain|return|exist|sit|post|fire)`), notAfter: endsWith(`is|are|be|only`)},
	{pattern: words(`no longer (?:an?|does|reads|runs|fires|handles|unwraps|posts|matters)`)},
	{pattern: words(`now lives (?:in|inside)`)},
	{pattern: words(`previously\s+(?:this|every|we|it)`)},
	{pattern: words(`equivalent of the old`)},
}

var filler = []string{
	"the", "a", "an", "and", "or", "of", "to", "in", "on", "at", "by", "for", "from", "with",
	"into", "onto", "this", "that", "these", "those", "it", "its", "we", "us", "our", "you",
	"your", "they", "them", "their", "he", "she", "his", "her", "is", "are", "was", "were",
	"be", "been", "being", "am", "do", "does", "did", "has", "have", "had", "will", "as",
	"here", "there", "then", "so", "up", "out", "per", "via", "each",
	"over", "off", "about", "upon", "across", "through", "between", "during", "within", "against",
	"back", "than", "but",
}

var strawman = []phrase{
	{pattern: words(`(?:intentionally|deliberately)\b[^.]{0,24}\b(?:not|never|no|empty|incomplete|omitted|unused)`)},
	{pattern: words(`(?:not|never)\b[^.]{0,40}\bon purpose`)},
	{pattern: words(`(?:is|are|'?s|'?re)\s+not\s+(?:in\s+this|here)`), notBefore: anothersContents},
	{pattern: words(`not\s+(?:stored|listed|included|present|defined|declared|kept|shown)\s+(?:here|in\s+this)`), notBefore: anothersContents},
}

var (
	negation         = words(`not|never|no|isn'?t|aren'?t|nothing`)
	strawmanNoun     = words(`random|arbitrary|magic|magical|blanket|coincidence|coincidental|accident|accidental|by chance|typo|mistake|dead code|courtesy|vibes|afterthought|oversight`)
	mistakeAsVerb    = endsWith(`not|never|can|could|may|might|must|will|would|should|to|do|does|did`)
	endsPhrase       = regexp.MustCompile(`^(?:\s*(?:[^\w\s]|$)|\s+(?:` + strings.Join(filler, "|") + `)\b)`)
	anothersContents = regexp.MustCompile(`^\s+\w+'s`)
)

// phrase is a pattern, and what must not stand right before or right after a match of it for the match to count.
type phrase struct {
	pattern   *regexp.Regexp
	notAfter  *regexp.Regexp
	notBefore *regexp.Regexp
}

func words(pattern string) *regexp.Regexp {
	return regexp.MustCompile(`(?i)\b(?:` + pattern + `)\b`)
}

func endsWith(alternatives string) *regexp.Regexp {
	return regexp.MustCompile(`(?i)\b(?:` + alternatives + `) $`)
}

func (p phrase) starts(text string) []int {
	var starts []int
	for _, match := range p.pattern.FindAllStringIndex(text, -1) {
		if p.notAfter != nil && p.notAfter.MatchString(text[:match[0]]) {
			continue
		}
		if p.notBefore != nil && p.notBefore.MatchString(text[match[1]:]) {
			continue
		}
		starts = append(starts, match[0])
	}

	return starts
}

var (
	letters      = regexp.MustCompile(`[^A-Za-z]+`)
	purpose      = regexp.MustCompile(`(?i)\bso\b`)
	condition    = regexp.MustCompile(`(?i)\b(?:if|whether|when|unless|where|whose|because)\b`)
	actionPhrase = regexp.MustCompile(`(?i)^used to (?:fire|hold|return|contain|post)\b`)
	clauseBreak  = regexp.MustCompile(`(?s)^.*(?:[.;:!?]|—)`)
)

func Words(text string) []string {
	var words []string
	for _, token := range letters.Split(spaceCamelCase(text), -1) {
		word := strings.ToLower(token)
		if len(word) < 2 || slices.Contains(filler, word) {
			continue
		}
		words = append(words, Stem(word))
	}

	return words
}

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

func spaceCamelCase(text string) string {
	var spaced strings.Builder
	var previous rune
	for at, char := range text {
		if at > 0 && unicode.IsUpper(char) && char <= unicode.MaxASCII && (unicode.IsLower(previous) || unicode.IsDigit(previous)) && previous <= unicode.MaxASCII {
			spaced.WriteByte(' ')
		}
		spaced.WriteRune(char)
		previous = char
	}

	return spaced.String()
}

func DefendsAgainstStrawman(text string) bool {
	starts := strawmanNegations(text)
	for _, phrase := range strawman {
		starts = append(starts, phrase.starts(text)...)
	}

	return slices.ContainsFunc(starts, func(start int) bool {
		return !isInConditionalClause(text, start) && !isInPurposeClause(text, start)
	})
}

func strawmanNegations(text string) []int {
	var starts []int
	for _, opened := range negation.FindAllStringIndex(text, -1) {
		for _, noun := range strawmanNoun.FindAllStringIndex(text[opened[1]:], -1) {
			between := text[opened[1] : opened[1]+noun[0]]
			if utf8.RuneCountInString(between) > 24 {
				break
			}
			at, end := opened[1]+noun[0], opened[1]+noun[1]
			if strings.ContainsAny(between, ".,;:—") || !endsPhrase.MatchString(text[end:]) {
				continue
			}
			if strings.EqualFold(text[at:end], "mistake") && mistakeAsVerb.MatchString(text[:at]) {
				continue
			}
			starts = append(starts, opened[0])

			break
		}
	}

	return starts
}

func NarratesHistory(text string) bool {
	for _, phrase := range history {
		for _, start := range phrase.starts(text) {
			if !isInConditionalClause(text, start) && !statesAPurpose(text, start) {
				return true
			}
		}
	}

	return false
}

func statesAPurpose(text string, start int) bool {
	return actionPhrase.MatchString(text[start:]) && strings.TrimSpace(clauseBefore(text, start)) == ""
}

func isInPurposeClause(text string, offset int) bool {
	return purpose.MatchString(clauseBefore(text, offset))
}

func isInConditionalClause(text string, offset int) bool {
	return condition.MatchString(clauseBefore(text, offset))
}

func clauseBefore(text string, offset int) string {
	return clauseBreak.ReplaceAllString(text[:offset], "")
}

func Paragraphs(lines []string, isProse func(string) bool) int {
	paragraphs, inParagraph := 0, false
	for _, line := range lines {
		if strings.TrimSpace(line) == "" {
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

func History(text string) bool {
	return slices.ContainsFunc(history, func(narration phrase) bool { return len(narration.starts(text)) > 0 })
}

func Strawman(text string) bool {
	return len(strawmanNegations(text)) > 0 ||
		slices.ContainsFunc(strawman, func(defence phrase) bool { return len(defence.starts(text)) > 0 })
}

func Trim(text string) string {
	return strings.Trim(text, " \t\n\r\x00\x0B")
}

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
