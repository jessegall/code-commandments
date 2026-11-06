package csharp

import (
	"encoding/xml"
	"errors"
	"io"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

var (
	lineBreak = regexp.MustCompile(`\r\n|\r|\n`)
	markup    = regexp.MustCompile(`<[^>]*>`)
)

var signatureTags = []string{"summary", "remarks", "param", "typeparam", "returns", "value"}

const prosePart = "#text"

// Comment is a C# comment as a detector reads it: the engine's comment, with what only C# knows about one — a doc comment's XML tags, the bridge's judgement that a comment holds code — read off it.
type Comment struct {
	engine.Commented
}

func (c Comment) IsDoc() bool {
	return c.Kind == "doc"
}

func (c Comment) IsCode() bool {
	return c.Extras != nil && c.Extras.CSharp != nil && c.Extras.CSharp.Code
}

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

func (t DocTag) IsAboutTheSignature() bool {
	return slices.Contains(signatureTags, t.Name)
}

func (t DocTag) IsDescription() bool {
	return t.Name == "summary" || t.Name == "remarks" || t.Name == prosePart
}

func (t DocTag) SaysNothingBeyond(words []string) bool {
	return !slices.ContainsFunc(prose.Words(markup.ReplaceAllString(t.XML, "")), func(word string) bool { return !slices.Contains(words, word) })
}

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

func (c Comment) RestatesOnly(words []string) bool {
	tags := c.Tags()

	return slices.ContainsFunc(tags, func(tag DocTag) bool { return !tag.IsDescription() && tag.IsAboutTheSignature() }) &&
		!slices.ContainsFunc(tags, func(tag DocTag) bool { return !tag.IsAboutTheSignature() || !tag.SaysNothingBeyond(words) })
}

func (c Comment) Documented() Node {
	return Node{c.Documents()}
}

func (c *Codebase) Comments() []Comment {
	var comments []Comment
	for _, comment := range c.Codebase.Comments() {
		comments = append(comments, Comment{Commented: comment})
	}

	return comments
}

func (n Node) CommentsAbove() []Comment {
	var above []Comment
	for _, comment := range n.Match.CommentsAbove() {
		held := Comment{Commented: engine.Commented{Comment: comment, File: n.Source()}}
		if !held.IsDoc() && !prose.IsFixtureMarker(held.Prose()) {
			above = append(above, held)
		}
	}

	return above
}

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

func (c *Codebase) WhereComment(check func(Comment) bool) []engine.Match {
	var found []engine.Match
	for _, comment := range c.Comments() {
		if check(comment) {
			found = append(found, comment.Match())
		}
	}

	return found
}
