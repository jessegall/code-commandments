package engine

import (
	"regexp"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

// Commented is one comment of the codebase with the file that holds it. A comment is no node of the tree — every
// bridge writes the comments of a file beside its nodes — so this is how a rule or a detector reaches one at all.
type Commented struct {
	contract.Comment
	File *File
}

// Comments is every comment of every file the codebase holds, in file order.
func (c *Codebase) Comments() []Commented {
	var comments []Commented
	for _, file := range c.files {
		for _, comment := range file.File.Comments {
			comments = append(comments, Commented{Comment: comment, File: file})
		}
	}

	return comments
}

// WhereComment opens a query over every comment of the codebase, each as the match that reports it. The checks of
// the query read the comment through that match — its text, its file and the line it is reported on — so a rule
// about what a comment says is written with the same steps a rule about code is.
func (c *Codebase) WhereComment() *Query {
	return &Query{selected: func(yield func(Match) bool) {
		for _, comment := range c.Comments() {
			if !yield(comment.Match()) {
				return
			}
		}
	}}
}

// lineBreak splits a comment into the lines a reader reads.
var lineBreak = regexp.MustCompile(`\r\n|\r|\n`)

// markup is a tag inside a comment: a doc comment's XML, and the tags of a markup comment.
var markup = regexp.MustCompile(`<[^>]*>`)

// opener is what a line of a comment is written WITH rather than said with: every language's comment markers, and
// the leading stars of a block comment's continued lines. Python's Sphinx `#:` is one of them.
var opener = regexp.MustCompile("^(//+|#:?|/\\*+|\\*+|\"\"\"|'''|<!--)+")

// closer is the marker a comment's last line ends with.
var closer = regexp.MustCompile("(\\*+/|\"\"\"|'''|-->)$")

// ProseLines is what the comment SAYS, as a reader reads it: its markers and any tags taken away, one line per
// line, each trimmed. It is the one reading of a comment's prose, so a judgement about prose is given the same
// text in every language.
func (cm Commented) ProseLines() []string {
	var lines []string
	for _, line := range lineBreak.Split(markup.ReplaceAllString(cm.Text, ""), -1) {
		said := strings.TrimSpace(line)
		said = strings.TrimSpace(opener.ReplaceAllString(said, ""))
		lines = append(lines, strings.TrimSpace(closer.ReplaceAllString(said, "")))
	}

	return lines
}

// Prose is what the comment says, as one line, which is what a judgement about prose reads.
func (cm Commented) Prose() string {
	var said []string
	for _, line := range cm.ProseLines() {
		if line != "" {
			said = append(said, line)
		}
	}

	return strings.Join(said, " ")
}

// Words are the content words the comment says, stemmed, each once.
func (cm Commented) Words() []string {
	var words []string
	for _, word := range prose.Words(cm.Prose()) {
		if !slices.Contains(words, word) {
			words = append(words, word)
		}
	}

	return words
}

// AsComment is the comment a comment's match stands for, found by where it starts in its file. No comment for any
// other match, since only a comment's match stands for one.
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

// Dangling says whether one of the references a doc comment makes names code that is gone: it resolves to
// nothing, AND the project would have had to own it. A reference the compiler could never have resolved anyway is
// not one of these, and neither is one whose owner is declared outside the scan, since nothing here can say
// whether that owner still has the member.
func (cm Commented) Dangling() bool {
	return slices.ContainsFunc(cm.Refs, Dangling)
}

// Dangling says whether the reference names code that is gone, as Commented.Dangling reads one reference.
func Dangling(ref contract.Ref) bool {
	if ref.Symbol != "" {
		return false
	}

	if ref.OwnedHere != nil && *ref.OwnedHere {
		return true
	}

	return ref.Owner == "" && !ref.Blind
}

// Judgements are the calibrated readings of a comment's prose, by the name a rule asks for one: prose a reader
// recognises, each measured by the prose package's own tests rather than by a pattern a rule writes.
var Judgements = []string{"history", "strawman", "restates"}

// Is says whether the comment is that kind of comment, by the name of one of the Judgements: `history` narrates
// why the code was changed, `strawman` defends against a mistake nobody was going to make, and `restates` says
// nothing the code it documents does not. A name none of them is false, since a judgement nobody calibrated is no
// judgement.
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

// Restates says whether the comment says nothing the code it documents does not: every content word it says is a
// word that code already spells. A comment of fewer than two content words says too little to judge, and a comment
// documenting code whose language spells no words for the engine is left alone.
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

// Documents is the code the comment is about: the node its bridge attached it to, which is the bridge's own answer
// and the only one for a comment written INSIDE what it documents, such as a Python docstring; else the first
// thing that can be documented starting after it, which is what a comment standing above its code means. No node
// where it is attached to nothing and nothing documentable follows it.
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

// Line is the line the comment is reported on: the line of the code it documents, so a finding points at the thing
// the comment is about, and its own line where it documents nothing.
func (cm Commented) Line() int {
	if documented := cm.Documents(); documented.Exists() {
		return documented.Line()
	}

	return cm.Span.Line
}

// Match is the comment as a match: a node of its own kind, spanning the comment and reported on its Line.
func (cm Commented) Match() Match {
	return cm.File.CommentMatch(cm.Comment, cm.Line())
}

// documentableIn is every node of the file a comment may be about, in the order they start: a statement, a
// declaration or a member, since those are the things a comment documents. An expression is no thing to document
// and a node whose role is `other` is a piece of syntax rather than a thing; a language refines that through its
// own Documentable, where its tree files something as a role the reader would not. Read once per file.
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

// documentable says whether a comment can be about the node: its language's own answer where it gives one, else
// anything that is neither an expression nor a piece of syntax.
func documentable(match Match) bool {
	if read := match.grammar().Documentable; read != nil {
		return read(match)
	}

	return match.Role() != "expression" && match.Role() != "other"
}
