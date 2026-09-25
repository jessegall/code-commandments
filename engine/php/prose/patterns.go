package prose

import (
	"regexp"
	"strings"
)

// historyPlain are the alternatives of PHP's Prose::HISTORY that need no lookaround.
var historyPlain = regexp.MustCompile(`(?i)\b(?:refactored|renamed from|ported from|is retired)\b` +
	`|\bno longer (?:an?|does|reads|runs|fires|handles|unwraps|posts|matters)\b` +
	`|\bnow lives (?:in|inside)\b` +
	`|\bpreviously\s+(?:this|every|we|it)\b` +
	`|\bequivalent of the old\b`)

var (
	formerly    = regexp.MustCompile(`(?i)\bformerly\b`)
	extracted   = regexp.MustCompile(`(?i)\bwas extracted\b`)
	fromFollows = regexp.MustCompile(`(?i)^\s+from`)
	usedTo      = regexp.MustCompile(`(?i)\bused to (?:be|live|have|hold|contain|return|exist|sit|post|fire)\b`)
)

// History says whether a text narrates the code's past, as PHP's Prose::HISTORY matches it: "formerly",
// "refactored", "used to be", "no longer does", "now lives in".
func History(text string) bool {
	if historyPlain.MatchString(text) {
		return true
	}
	for _, at := range formerly.FindAllStringIndex(text, -1) {
		if !precededByAny(text, at[0], []string{"a", "an", "the"}) {
			return true
		}
	}
	for _, at := range extracted.FindAllStringIndex(text, -1) {
		if !fromFollows.MatchString(text[at[1]:]) {
			return true
		}
	}
	for _, at := range usedTo.FindAllStringIndex(text, -1) {
		if !precededByAny(text, at[0], []string{"is", "are", "be", "only"}) {
			return true
		}
	}

	return false
}

var (
	negation     = regexp.MustCompile(`(?i)\b(?:not|never|no|isn'?t|aren'?t|nothing)\b`)
	strawmen     = []string{"random", "arbitrary", "magic", "magical", "blanket", "coincidence", "coincidental", "accident", "accidental", "by chance", "typo", "mistake", "dead code", "courtesy", "vibes", "afterthought", "oversight"}
	mistakeAfter = []string{"not", "never", "can", "could", "may", "might", "must", "will", "would", "should", "to", "do", "does", "did"}
	disclaimed   = regexp.MustCompile(`(?i)\b(?:intentionally|deliberately)\b[^.]{0,24}\b(?:not|never|no|empty|incomplete|omitted|unused)\b` +
		`|\b(?:not|never)\b[^.]{0,40}\bon purpose\b`)
	notHere   = regexp.MustCompile(`(?i)\b(?:is|are|'?s|'?re)\s+not\s+(?:in\s+this\b|here\b)`)
	notKept   = regexp.MustCompile(`(?i)\bnot\s+(?:stored|listed|included|present|defined|declared|kept|shown)\s+(?:here\b|in\s+this\b)`)
	possessor = regexp.MustCompile(`^\s+\w+'s`)
	closes    = regexp.MustCompile(`^\s*(?:[^\w\s]|\n?$)`)
	fillerOn  = regexp.MustCompile(`(?i)^\s+(?:` + strings.Join(filler, "|") + `)\b`)
)

// Strawman says whether a text defends the code against a charge nobody made, as PHP's Prose::strawman() matches
// it: "not random", "deliberately empty", "not stored here".
func Strawman(text string) bool {
	if disclaimed.MatchString(text) {
		return true
	}
	for _, pattern := range []*regexp.Regexp{notHere, notKept} {
		for _, at := range pattern.FindAllStringIndex(text, -1) {
			if !strings.HasSuffix(strings.ToLower(text[at[0]:at[1]]), "this") || !possessor.MatchString(text[at[1]:]) {
				return true
			}
		}
	}
	for _, at := range negation.FindAllStringIndex(text, -1) {
		if deniesAStrawman(text, at[1]) {
			return true
		}
	}

	return false
}

// deniesAStrawman says whether, within 24 bytes of a negation ending at the offset and before any clause break, a
// strawman word stands closing its clause.
func deniesAStrawman(text string, from int) bool {
	for gap := 0; gap <= 24; gap++ {
		at := from + gap
		if at > len(text) {
			return false
		}
		if gap > 0 && breaksClause(text, at-1) {
			return false
		}
		if boundary(text, at) && strawmanAt(text, at) {
			return true
		}
	}

	return false
}

// breaksClause says whether the byte at the offset stops the gap: a clause mark, or the start of an em dash.
func breaksClause(text string, at int) bool {
	return strings.ContainsRune(".,;:", rune(text[at])) || strings.HasPrefix(text[at:], "—")
}

func strawmanAt(text string, at int) bool {
	for _, word := range strawmen {
		end := at + len(word)
		if end > len(text) || !strings.EqualFold(text[at:end], word) || !boundary(text, end) {
			continue
		}
		if word == "mistake" && precededByAny(text, at, mistakeAfter) {
			continue
		}
		if closes.MatchString(text[end:]) || fillerOn.MatchString(text[end:]) {
			return true
		}
	}

	return false
}
