// Package prose reduces English to the words that carry meaning, and reads what a sentence does: narrate the
// code's past, or defend it against a reading nobody made. It is the one home every language's documentation
// rules read comments through. Where a pattern must not follow or precede something, the phrase says so beside it.
package prose

import (
	"regexp"
	"slices"
	"strings"
	"unicode"
	"unicode/utf8"
)

// history is the phrases that narrate a change to the code — what it was, where it moved, what it no longer is —
// rather than what it IS. Runtime state ("no longer exists", "previously bound") is not history.
var history = []phrase{
	// "a formerly enqueued task" is runtime state
	{pattern: words(`formerly`), notAfter: endsWith(`a|an|the`)},
	{pattern: words(`refactored|renamed from|ported from|is retired`)},
	// "this was extracted from the request" is runtime state too
	{pattern: words(`was extracted`), notBefore: regexp.MustCompile(`^\s+from`)},
	// "is used to hold" / "only used to be" mean "used in order to", not "once was"
	{pattern: words(`used to (?:be|live|have|hold|contain|return|exist|sit|post|fire)`), notAfter: endsWith(`is|are|be|only`)},
	// "no longer an X" / "no longer <does>" — but NOT runtime state (exists/matches/contains/fits)
	{pattern: words(`no longer (?:an?|does|reads|runs|fires|handles|unwraps|posts|matters)`)},
	{pattern: words(`now lives (?:in|inside)`)},
	// clause-initial "previously this/every/we/it…" narration — excludes runtime "previously bound"
	{pattern: words(`previously\s+(?:this|every|we|it)`)},
	{pattern: words(`equivalent of the old`)},
}

// filler is pure grammar: the words a sentence needs to hold together, carrying no information itself.
var filler = []string{
	"the", "a", "an", "and", "or", "of", "to", "in", "on", "at", "by", "for", "from", "with",
	"into", "onto", "this", "that", "these", "those", "it", "its", "we", "us", "our", "you",
	"your", "they", "them", "their", "he", "she", "his", "her", "is", "are", "was", "were",
	"be", "been", "being", "am", "do", "does", "did", "has", "have", "had", "will", "as",
	"here", "there", "then", "so", "up", "out", "per", "via", "each",
	// Prepositions a sentence needs and code never spells — "loop OVER the entries", "read it BACK", "pull
	// them THROUGH". Dropping them is what lets a narration line up with its statement.
	"over", "off", "about", "upon", "across", "through", "between", "during", "within", "against",
	"back", "than", "but",
}

// strawman is the phrases that defend code against a reading nobody made, instead of saying what it IS.
var strawman = []phrase{
	// an intent adverb defending a negation or an absence
	{pattern: words(`(?:intentionally|deliberately)\b[^.]{0,24}\b(?:not|never|no|empty|incomplete|omitted|unused)`)},
	// a negation excused as deliberate
	{pattern: words(`(?:not|never)\b[^.]{0,40}\bon purpose`)},
	// pointing at an absence: a named thing that is not present here, or in this list — not in another thing's
	// contents ("this source's map")
	{pattern: words(`(?:is|are|'?s|'?re)\s+not\s+(?:in\s+this|here)`), notBefore: anothersContents},
	{pattern: words(`not\s+(?:stored|listed|included|present|defined|declared|kept|shown)\s+(?:here|in\s+this)`), notBefore: anothersContents},
}

// negation opens a strawman phrase whose noun ends it: "not random", "no oversight". The noun must end its phrase —
// what follows is punctuation or grammar — so an adjective on a content noun ("a system random number generator")
// is left alone; and `mistake` after a modal or `to` is the verb.
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

// words is a case-insensitive pattern that starts and ends on word boundaries.
func words(pattern string) *regexp.Regexp {
	return regexp.MustCompile(`(?i)\b(?:` + pattern + `)\b`)
}

// endsWith is a pattern for text ending in one of the words and a space: what a phrase must not follow.
func endsWith(alternatives string) *regexp.Regexp {
	return regexp.MustCompile(`(?i)\b(?:` + alternatives + `) $`)
}

// starts is where each match of the phrase starts that nothing around it rules out.
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

// Words is the content words of the text, lower-cased and stemmed, in order: grammar dropped, and anything that
// is not a letter treated as a separator, so `order.totalPrice()` and "order total price" reduce alike.
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

// Stem is a crude, symmetric stem: one plural or participle ending stripped, then a trailing `e`, so both sides of
// a comparison land on the same token (`settle`/`settled` → `settl`, `price`/`prices` → `pric`). It stops at three
// characters, so `used` keeps its shape.
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

// spaceCamelCase breaks camelCase and PascalCase runs apart so an identifier tokenises into its words: `totalPrice`
// → `total Price`. Snake and kebab separators need no help; they are not letters.
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

// DefendsAgainstStrawman says whether the text defends the code against a strawman instead of stating what it is.
func DefendsAgainstStrawman(text string) bool {
	starts := strawmanNegations(text)
	for _, phrase := range strawman {
		starts = append(starts, phrase.starts(text)...)
	}

	return slices.ContainsFunc(starts, func(start int) bool {
		return !isInConditionalClause(text, start) && !isInPurposeClause(text, start)
	})
}

// strawmanNegations is where each negation starts that a strawman noun ends within its clause: at most 24
// characters on, with no clause break between.
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

// NarratesHistory says whether the text narrates the code's past instead of its present, outside a clause opened
// by a condition or a place, which describes what happens at runtime.
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

// statesAPurpose says whether the match opens its clause with a purpose: the phrase before an action verb (fire,
// hold, return, contain, post), saying what a thing is for. Before a verb of state the same opening names a past
// state.
func statesAPurpose(text string, start int) bool {
	return actionPhrase.MatchString(text[start:]) && strings.TrimSpace(clauseBefore(text, start)) == ""
}

// isInPurposeClause says whether the clause holding the offset says what the code makes sure of: a `so` since the
// last sentence or clause break, as in "so a test cannot pass by coincidence".
func isInPurposeClause(text string, offset int) bool {
	return purpose.MatchString(clauseBefore(text, offset))
}

// isInConditionalClause says whether the clause holding the offset opens a condition, a place, an owner or a
// reason: an `if`, `whether`, `when`, `unless`, `where`, `whose` or `because` since the last break.
func isInConditionalClause(text string, offset int) bool {
	return condition.MatchString(clauseBefore(text, offset))
}

// clauseBefore is the part of the clause holding the offset that comes before it: the text since the last sentence
// or clause break.
func clauseBefore(text string, offset int) string {
	return clauseBreak.ReplaceAllString(text[:offset], "")
}

// Paragraphs is how many paragraphs of prose the lines hold: runs of lines isProse accepts, a blank line ending
// one. A line that is neither prose nor blank, a tag or an example, leaves the paragraph as it is.
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

// History says whether a phrase that narrates the code's past stands anywhere in the text, whatever clause holds it:
// the pattern alone, which PHP's documentation rules match a comment against.
func History(text string) bool {
	return slices.ContainsFunc(history, func(narration phrase) bool { return len(narration.starts(text)) > 0 })
}

// Strawman says whether a phrase that defends the code against a reading nobody made stands anywhere in the text,
// whatever clause holds it: the pattern alone, which PHP's documentation rules match a comment against.
func Strawman(text string) bool {
	return len(strawmanNegations(text)) > 0 ||
		slices.ContainsFunc(strawman, func(defence phrase) bool { return len(defence.starts(text)) > 0 })
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
