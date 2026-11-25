package engine

import (
	"regexp"
	"slices"
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

func (c *Codebase) CommentRuns() []Commented {
	var comments []Commented
	for _, file := range c.owned() {
		for _, comment := range file.File.Comments {
			current := Commented{Comment: comment, File: file}
			if current.Prose() == "" {
				continue
			}
			if at := len(comments) - 1; at >= 0 && comments[at].continuesWith(current) {
				comments[at].Text += "\n" + current.Text
				comments[at].Span.End = current.Span.End
				comments[at].Refs = append(slices.Clone(comments[at].Refs), current.Refs...)
				continue
			}
			comments = append(comments, current)
		}
	}

	return comments
}

func (cm Commented) continuesWith(next Commented) bool {
	return cm.File == next.File && cm.Form == "line" && next.Form == "line" && !cm.Trailing && !next.Trailing &&
		!cm.IsCode() && !next.IsCode() && next.Span.Line == cm.Span.Line+strings.Count(cm.Text, "\n")+1 &&
		cm.Attached != nil && next.Attached != nil && *cm.Attached == *next.Attached
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
	for _, line := range lineBreak.Split(cm.Text, -1) {
		said := strings.TrimSpace(line)
		said = strings.TrimSpace(closer.ReplaceAllString(said, ""))
		said = strings.TrimSpace(opener.ReplaceAllString(said, ""))
		said = strings.TrimSpace(markup.ReplaceAllString(said, ""))
		if !prose.IsFixtureMarker(said) {
			lines = append(lines, said)
		}
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

// Description is what the comment says in prose: the description its bridge read from a doc comment, or else its
// prose lines with tag lines left out.
func (cm Commented) Description() []string {
	if cm.Comment.Description != nil || cm.Comment.Tags != nil {
		return cm.Comment.Description
	}

	return slices.DeleteFunc(cm.ProseLines(), func(line string) bool { return strings.HasPrefix(line, "@") })
}

func (cm Commented) NarratesHistory() bool {
	return prose.NarratesHistory(strings.Join(cm.Description(), " "))
}

func (cm Commented) DefendsAgainstStrawman() bool {
	return prose.DefendsAgainstStrawman(strings.Join(cm.Description(), " "))
}

// IsCode says whether the comment's words are commented-out code: the bridge's parser read them as code with no
// diagnostic, and they are more than a lone word or a quoted phrase, which read as code in every language and are prose.
func (cm Commented) IsCode() bool {
	fragment := cm.Fragment
	if fragment == nil || fragment.Diagnostics > 0 || fragment.Statements == 0 {
		return false
	}

	return fragment.Statements > 1 || fragment.ExpressionKind != "reference" && fragment.ExpressionKind != "literal"
}

func (cm Commented) RestatesStatement() bool {
	owner := cm.Documents()
	return cm.Form != "doc" && !cm.IsCode() && owner.Exists() && !owner.Is(Block) && !owner.Is(Function) &&
		!owner.Is(TypeDeclaration) && owner.IsWithinFunction() && !cm.headsASection(owner) && cm.Restates()
}

// headsASection reports a comment a blank line keeps apart from the statement after it: it labels what follows, not
// that one statement.
func (cm Commented) headsASection(owner Match) bool {
	source, err := cm.File.Source()
	if err != nil || cm.Span.End > owner.node.Span.Start {
		return false
	}

	return HasBlankLine(string(source[cm.Span.End:owner.node.Span.Start]))
}

var blankLine = regexp.MustCompile(`\n[ \t]*\r?\n`)

// HasBlankLine reports text holding an empty line: one with nothing on it but whitespace.
func HasBlankLine(text string) bool {
	return blankLine.MatchString(text)
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
	if slices.ContainsFunc(cm.Refs, Dangling) {
		return true
	}
	return false
}

func Dangling(ref contract.Ref) bool {
	if ref.Symbol != "" {
		return false
	}

	if ref.OwnedHere != nil {
		return *ref.OwnedHere
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
	if cm.Attached == nil {
		return Match{}
	}

	return cm.File.Match(*cm.Attached)
}

func (cm Commented) StackedOn() Match {
	if cm.Form != "doc" || cm.Attached != nil || cm.headsTheFile() {
		return Match{}
	}
	comments := cm.File.File.Comments
	at := slices.IndexFunc(comments, func(comment contract.Comment) bool { return comment.Span.Start >= cm.Span.End })
	if at < 0 || comments[at].Form != "doc" || comments[at].Attached == nil {
		return Match{}
	}
	next := comments[at]
	source, err := cm.File.Source()
	if err != nil || next.Span.Start > len(source) || strings.TrimSpace(string(source[cm.Span.End:next.Span.Start])) != "" {
		return Match{}
	}

	return cm.File.Match(*next.Attached)
}

func (cm Commented) headsTheFile() bool {
	return !cm.File.Any(func(node *contract.Node) bool {
		return node.Kind != "file" && node.Kind != "module" && node.Span.Start < cm.Span.Start
	}) && !slices.ContainsFunc(cm.File.File.Comments, func(comment contract.Comment) bool { return comment.Span.Start < cm.Span.Start })
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

// Location is where a finding on the comment reports: the node it documents, or the comment itself when it documents
// none.
func (cm Commented) Location() Match {
	if documented := cm.Documents(); documented.Exists() {
		return documented
	}

	return cm.Match()
}
