package csharp

import (
	"encoding/xml"
	"sort"
	"errors"
	"io"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

var (
	// lineBreak is any line break.
	lineBreak = regexp.MustCompile(`\r\n|\r|\n`)
	// markup is an XML tag, as a doc comment writes one.
	markup = regexp.MustCompile(`<[^>]*>`)
)

// signatureTags are the doc tags that describe a member's signature.
var signatureTags = []string{"summary", "remarks", "param", "typeparam", "returns", "value"}

// prosePart is a run of a doc comment's prose outside any tag.
const prosePart = "#text"

// Comment is a C# comment as a detector reads it: a line, block or doc comment, in the file it is written in.
type Comment struct {
	contract.Comment
	File *engine.File
}

// IsDoc says whether the comment is a documentation comment.
func (c Comment) IsDoc() bool {
	return c.Kind == "doc"
}

// IsCode says whether the line or block comment parses as one C# statement, as the bridge judged it.
func (c Comment) IsCode() bool {
	return c.Extras != nil && c.Extras.CSharp != nil && c.Extras.CSharp.Code
}

// ProseLines is the words the comment says, as a reader reads them: its markers and a doc comment's XML tags taken
// away, one line per line.
func (c Comment) ProseLines() []string {
	body := c.Text
	if c.Kind == "block" && len(body) >= 4 {
		body = body[2 : len(body)-2]
	}
	var lines []string
	for _, line := range lineBreak.Split(markup.ReplaceAllString(body, ""), -1) {
		lines = append(lines, strings.TrimSpace(strings.TrimLeft(strings.TrimSpace(line), "/*")))
	}

	return lines
}

// Prose is the comment's words as one line.
func (c Comment) Prose() string {
	var kept []string
	for _, line := range c.ProseLines() {
		if line != "" {
			kept = append(kept, line)
		}
	}

	return strings.TrimSpace(strings.Join(kept, " "))
}

// Paragraphs is how many paragraphs of prose the comment holds: runs of words, a blank line or a tag on a line of
// its own ending one, and each `<para>` one of its own. A doc comment counts only its `<summary>` and `<remarks>`.
func (c Comment) Paragraphs() int {
	sections := []string{strings.Join(c.ProseLines(), "\n")}
	if c.IsDoc() {
		sections = nil
		for _, tag := range c.Tags() {
			if tag.IsDescription() {
				sections = append(sections, tag.XML)
			}
		}
	}
	total := 0
	for _, section := range sections {
		spaced := strings.NewReplacer("<para>", "\n\n", "</para>", "\n\n").Replace(section)
		var lines []string
		for _, line := range strings.Split(markup.ReplaceAllString(spaced, ""), "\n") {
			lines = append(lines, strings.TrimSpace(line))
		}
		total += prose.Paragraphs(lines, func(line string) bool { return line != "" })
	}

	return total
}

// DocTag is one top-level part of a doc comment: a tag with its XML as written, or a run of prose outside one.
type DocTag struct {
	Name string
	XML  string
}

// IsAboutTheSignature says whether the tag describes the member's signature.
func (t DocTag) IsAboutTheSignature() bool {
	return slices.Contains(signatureTags, t.Name)
}

// IsDescription says whether the tag describes the member rather than its contract: its summary, its remarks, or
// prose outside a tag.
func (t DocTag) IsDescription() bool {
	return t.Name == "summary" || t.Name == "remarks" || t.Name == prosePart
}

// SaysNothingBeyond says whether every word the tag says is one of the words.
func (t DocTag) SaysNothingBeyond(words []string) bool {
	return !slices.ContainsFunc(prose.Words(markup.ReplaceAllString(t.XML, "")), func(word string) bool { return !slices.Contains(words, word) })
}

// Tags is the top-level parts of the doc comment, in order: each tag, and each run of prose outside one; the whole
// comment as one run of prose when it does not read as XML.
func (c Comment) Tags() []DocTag {
	var lines []string
	for _, line := range strings.Split(c.Text, "\n") {
		lines = append(lines, strings.TrimLeft(strings.TrimSpace(line), "/"))
	}
	body := strings.Join(lines, "\n")
	document := "<doc>" + body + "</doc>"
	decoder := xml.NewDecoder(strings.NewReader(document))
	decoder.Strict = true
	var tags []DocTag
	depth, opened := 0, int64(0)
	for {
		before := decoder.InputOffset()
		token, err := decoder.Token()
		if err != nil {
			if errors.Is(err, io.EOF) && depth == 0 {
				return tags
			}

			return []DocTag{{Name: prosePart, XML: body}}
		}
		switch part := token.(type) {
		case xml.StartElement:
			depth++
			if depth == 2 {
				opened = before
			}
		case xml.EndElement:
			if depth == 2 {
				tags = append(tags, DocTag{Name: part.Name.Local, XML: document[opened:decoder.InputOffset()]})
			}
			depth--
		case xml.CharData:
			if depth == 1 && strings.TrimSpace(string(part)) != "" {
				tags = append(tags, DocTag{Name: prosePart, XML: string(part)})
			}
		}
	}
}

// RestatesOnly says whether the doc comment describes the signature and says nothing beyond the words: at least one
// tag for a part of it, and every tag empty or made only of those words.
func (c Comment) RestatesOnly(words []string) bool {
	tags := c.Tags()

	return slices.ContainsFunc(tags, func(tag DocTag) bool { return !tag.IsDescription() && tag.IsAboutTheSignature() }) &&
		!slices.ContainsFunc(tags, func(tag DocTag) bool { return !tag.IsAboutTheSignature() || !tag.SaysNothingBeyond(words) })
}

// IsDangling says whether the doc reference names nothing the code has: it resolves to nothing, and either the
// qualifier that does resolve is declared here, or nothing of it resolves though the compiler could have seen it.
func IsDangling(ref contract.Ref) bool {
	if ref.Symbol != "" {
		return false
	}
	ownedHere := ref.OwnedHere != nil && *ref.OwnedHere

	return ownedHere || (ref.Owner == "" && !ref.Blind)
}

// Documented is the code the comment is about: the first declaration or statement after it, the outermost of
// those starting there; no node when nothing follows.
func (c Comment) Documented() Node {
	documentable := Of(c.File.Codebase()).documentable(c.File)
	at := sort.Search(len(documentable), func(i int) bool { return documentable[i].Node().Span.Start >= c.Span.End })
	if at == len(documentable) {
		return Node{}
	}

	return documentable[at]
}

// Line is the line the comment is reported on: the line of the code it documents, or its own when it documents
// nothing.
func (c Comment) Line() int {
	if documented := c.Documented(); documented.Exists() {
		return documented.Line()
	}

	return c.Span.Line
}

// Comments is every comment in the C# files of the codebase.
func (c *Codebase) Comments() []Comment {
	var comments []Comment
	for _, file := range c.Files() {
		for _, comment := range file.File.Comments {
			comments = append(comments, Comment{Comment: comment, File: file})
		}
	}

	return comments
}

// CommentsAbove is the run of line and block comments standing directly above the statement, fixture markers set
// aside.
func (n Node) CommentsAbove() []Comment {
	var above []Comment
	for _, comment := range n.Match.CommentsAbove() {
		held := Comment{Comment: comment, File: n.Source()}
		if !held.IsDoc() && !prose.IsFixtureMarker(held.Prose()) {
			above = append(above, held)
		}
	}

	return above
}

// CommentWords is the content words the comments above the statement say, stemmed; none when one of them holds code.
func (n Node) CommentWords() []string {
	above := n.CommentsAbove()
	if slices.ContainsFunc(above, Comment.IsCode) {
		return nil
	}
	var said []string
	for _, comment := range above {
		said = append(said, comment.Prose())
	}
	var words []string
	for _, word := range prose.Words(strings.Join(said, " ")) {
		if !slices.Contains(words, word) {
			words = append(words, word)
		}
	}

	return words
}

// WhereComment is the code documented by every comment the check keeps, each where the comment is reported: a
// comment that documents nothing is reported nowhere.
func (c *Codebase) WhereComment(check func(Comment) bool) []engine.Match {
	var found []engine.Match
	for _, comment := range c.Comments() {
		if documented := comment.Documented(); documented.Exists() && check(comment) {
			found = append(found, documented.Match)
		}
	}

	return found
}
