package engine

import (
	"regexp"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

// Commented is one comment of the codebase with the file that holds it.
type Commented struct {
	contract.Comment
	File *File
}

func (c *Codebase) Comments() []Commented {
	var comments []Commented
	for _, file := range c.files {
		for _, comment := range file.File.Comments {
			comments = append(comments, Commented{Comment: comment, File: file})
		}
	}

	return comments
}

func (c *Codebase) WhereComment() *Query {
	queries.Add(1)

	return &Query{selected: func(yield func(Match) bool) {
		for _, comment := range c.Comments() {
			if !yield(comment.Match()) {
				return
			}
		}
	}}
}

var lineBreak = regexp.MustCompile(`\r\n|\r|\n`)

var markup = regexp.MustCompile(`<[^>]*>`)

var opener = regexp.MustCompile("^(//+|#:?|/\\*+|\\*+|\"\"\"|'''|<!--)+")

var closer = regexp.MustCompile("(\\*+/|\"\"\"|'''|-->)$")

func (cm Commented) ProseLines() []string {
	var lines []string
	for _, line := range lineBreak.Split(markup.ReplaceAllString(cm.Text, ""), -1) {
		said := strings.TrimSpace(line)
		said = strings.TrimSpace(opener.ReplaceAllString(said, ""))
		lines = append(lines, strings.TrimSpace(closer.ReplaceAllString(said, "")))
	}

	return lines
}

func (cm Commented) Prose() string {
	var said []string
	for _, line := range cm.ProseLines() {
		if line != "" {
			said = append(said, line)
		}
	}

	return strings.Join(said, " ")
}

func (cm Commented) Words() []string {
	var words []string
	for _, word := range prose.Words(cm.Prose()) {
		if !slices.Contains(words, word) {
			words = append(words, word)
		}
	}

	return words
}

func (m Match) AsComment() (Commented, bool) {
	if m.node == nil || m.node.Kind != CommentKind || m.file == nil {
		return Commented{}, false
	}

	for _, comment := range m.file.File.Comments {
		if comment.Span.Start == m.node.Span.Start {
			return Commented{Comment: comment, File: m.file}, true
		}
	}

	return Commented{}, false
}

func (cm Commented) Dangling() bool {
	return slices.ContainsFunc(cm.Refs, Dangling)
}

func Dangling(ref contract.Ref) bool {
	if ref.Symbol != "" {
		return false
	}

	if ref.OwnedHere != nil && *ref.OwnedHere {
		return true
	}

	return ref.Owner == "" && !ref.Blind
}

var Judgements = []string{"history", "strawman", "restates"}

func (cm Commented) Is(judgement string) bool {
	switch judgement {
	case "history":
		return prose.NarratesHistory(cm.Prose())
	case "strawman":
		return prose.DefendsAgainstStrawman(cm.Prose())
	case "restates":
		return cm.Restates()
	}

	return false
}

func (cm Commented) Restates() bool {
	said := cm.Words()
	if len(said) < 2 {
		return false
	}

	spelled := cm.Documents().CodeWords()
	if len(spelled) == 0 {
		return false
	}

	return !slices.ContainsFunc(said, func(word string) bool { return !slices.Contains(spelled, word) })
}

func (cm Commented) Documents() Match {
	if cm.Attached != nil {
		return cm.File.Match(*cm.Attached)
	}

	documentable := documentableIn(cm.File)
	at := sort.Search(len(documentable), func(i int) bool { return documentable[i].node.Span.Start >= cm.Span.End })
	if at == len(documentable) {
		return Match{}
	}

	return documentable[at]
}

func (cm Commented) Line() int {
	if documented := cm.Documents(); documented.Exists() {
		return documented.Line()
	}

	return cm.Span.Line
}

func (cm Commented) Match() Match {
	return cm.File.CommentMatch(cm.Comment, cm.Line())
}

func documentableIn(file *File) []Match {
	return Analysis(file.Codebase(), documentableOf{file}, func(*Codebase) []Match {
		var kept []Match
		for _, match := range file.Match(0).Descendants() {
			if documentable(match) {
				kept = append(kept, match)
			}
		}

		return kept
	})
}

// documentableOf keys one file's documentable nodes as an analysis of the codebase.
type documentableOf struct {
	file *File
}

func documentable(match Match) bool {
	if read := match.grammar().Documentable; read != nil {
		return read(match)
	}

	return match.Role() != "expression" && match.Role() != "other"
}
