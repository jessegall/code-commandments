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

func (c *Codebase) CommentRuns() []Commented {
	var comments []Commented
	for _, file := range c.opened() {
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
	return cm.File == next.File && cm.Kind == "line" && next.Kind == "line" && !cm.Trailing && !next.Trailing &&
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

func (cm Commented) Description() []string {
	if read := grammarOf(cm.File.Language()).CommentProse; read != nil {
		return read(cm)
	}
	return slices.DeleteFunc(cm.ProseLines(), func(line string) bool { return strings.HasPrefix(line, "@") })
}

func (cm Commented) NarratesHistory() bool {
	return prose.NarratesHistory(strings.Join(cm.Description(), " "))
}

func (cm Commented) DefendsAgainstStrawman() bool {
	return prose.DefendsAgainstStrawman(strings.Join(cm.Description(), " "))
}

func (cm Commented) IsCode() bool {
	extra := cm.Extras
	return extra != nil && (extra.PHP != nil && extra.PHP.Code || extra.Python != nil && extra.Python.Code ||
		extra.CSharp != nil && extra.CSharp.Code || extra.Go != nil && extra.Go.Code || extra.TypeScript != nil && extra.TypeScript.Code)
}

func (cm Commented) RestatesStatement() bool {
	owner := cm.Documents()
	return cm.Kind != "doc" && !cm.IsCode() && owner.Exists() && !owner.Is(Block) && !owner.Is(Function) &&
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

func (cm Commented) IsBloatedDoc() bool {
	if cm.Kind != "doc" {
		return false
	}
	documented := cm.Documents()
	grammar := grammarOf(cm.File.Language())
	if read := grammar.DocumentedSubject; read != nil {
		documented = read(documented)
	}
	if !documented.Is(TypeDeclaration) {
		return false
	}
	if read := grammar.DocParagraphs; read != nil {
		return read(cm) >= 2
	}

	return prose.Paragraphs(prose.WithoutFencedExamples(cm.Description()), func(line string) bool { return line != "" }) >= 2
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
	read := grammarOf(cm.File.Language()).CommentReferences
	return cm.Kind == "doc" && read != nil && slices.ContainsFunc(read(cm), Dangling)
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

func (cm Commented) Location() Match {
	if read := grammarOf(cm.File.Language()).CommentLocation; read != nil {
		return read(cm)
	}
	return cm.Match()
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
