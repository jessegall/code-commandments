package engine

import (
	"bytes"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

const (
	FrozenMarker    = "@code-commandments-frozen"
	GeneratedMarker = "@code-commandments-generated"
)

var frozenTag = regexp.MustCompile(`(?i)@frozen\b`)

// Freezer says whether a file's own syntax declares it frozen, beyond a comment that says so.
type Freezer func(*File) bool

var freezers = map[contract.Language][]Freezer{}

var hints []func(path string, source []byte) bool

func Freezes(language contract.Language, freezer Freezer, hint func(path string, source []byte) bool) {
	freezers[language] = append(freezers[language], freezer)
	hints = append(hints, hint)
}

func (f *File) IsFrozen() bool {
	f.freezing.Do(func() {
		f.frozen = f.declaresItselfFrozen()
	})

	return f.frozen
}

func (f *File) declaresItselfFrozen() bool {
	source, err := f.Source()
	if err != nil {
		return false
	}
	language := languageOfPath(f.Path)
	for _, freezer := range freezers[language] {
		if freezer(f) {
			return true
		}
	}
	if language == contract.PHP {
		for _, comment := range f.File.Comments {
			if declaresFreeze(comment.Text) {
				return true
			}
		}

		return false
	}
	for _, line := range strings.Split(string(source), "\n") {
		if contract.IsCommentLine(language, line) && declaresFreeze(line) {
			return true
		}
	}

	return contract.IsGenerated(language, f.Path, headingOf(language, string(source)))
}

func MayBeFrozen(path string, source []byte) bool {
	if languageOfPath(path) == contract.CSharp || bytes.Contains(bytes.ToLower(source), []byte("frozen")) || bytes.Contains(source, []byte(GeneratedMarker)) {
		return true
	}

	return slices.ContainsFunc(hints, func(hint func(string, []byte) bool) bool { return hint(path, source) })
}

func FreezeReadsATree(path string) bool {
	_, declared := freezers[languageOfPath(path)]

	return declared
}

// languageOfPath is the file's language by its extension; none for a file of no language the tool reads.
func languageOfPath(path string) contract.Language {
	language, _ := contract.LanguageOfPath(path)

	return language
}

func declaresFreeze(comment string) bool {
	return strings.Contains(comment, FrozenMarker) || strings.Contains(comment, GeneratedMarker) || frozenTag.MatchString(comment)
}

func headingOf(language contract.Language, source string) string {
	var heading []string
	for _, line := range strings.Split(trimBytes(source, "\xef\xbb\xbf"), "\n") {
		if strings.Trim(line, phpSpace) != "" && !contract.IsCommentLine(language, line) {
			break
		}
		heading = append(heading, line)
	}

	return strings.Join(heading, "\n")
}

func trimBytes(source, set string) string {
	start := 0
	for start < len(source) && strings.IndexByte(set, source[start]) >= 0 {
		start++
	}

	return source[start:]
}
