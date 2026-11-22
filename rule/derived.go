package rule

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

// derived is the fields of a record worked out from what the record describes when a rule asks for one, so a record
// that many rules read keeps one field for them and stays small enough for the smallest map.
type derived interface {
	field(name string) (value, bool)
	names() []string
}

const derivedAt = "\x00derived"

func (r record) field(name string) (value, bool) {
	if held, found := r[name]; found {
		return held, true
	}
	from, isDerived := r[derivedAt].held.(derived)
	if !isDerived {
		return unknown, false
	}

	return from.field(name)
}

func (r record) whole() record {
	from, isDerived := r[derivedAt].held.(derived)
	if !isDerived {
		return r
	}
	fields := make(record, len(r)+len(from.names()))
	for name, held := range r {
		if name != derivedAt {
			fields[name] = held
		}
	}
	for _, name := range from.names() {
		fields[name], _ = from.field(name)
	}

	return fields
}

// placedSite is where a program record's site lies: its file, module, whether it is a test, and whether the file is reported.
type placedSite struct{ site engine.Match }

func (placedSite) names() []string {
	return []string{"file", "module", "test", "held"}
}

func (placed placedSite) field(name string) (value, bool) {
	file := placed.site.Source()
	switch name {
	case "file":
		return known(file.Path), true
	case "module":
		return known(file.Module), true
	case "test":
		return known(file.Purpose() != engine.Ordinary), true
	case "held":
		return known(placed.site.Codebase().Reports(file)), true
	}

	return unknown, false
}

// commentWords is what a comment run says: its prose, its raw text, its description and tags, its words, its
// paragraphs, the code words of what it documents and how many doc comments that holds.
type commentWords struct {
	comment    engine.Commented
	documented engine.Match
	docs       map[engine.Match]int
}

func (commentWords) names() []string {
	return []string{"text", "raw", "description", "tags", "words", "paragraphs", "codeWords", "docsOnOwner"}
}

func (words commentWords) field(name string) (value, bool) {
	comment := words.comment
	switch name {
	case "text":
		return known(comment.Prose()), true
	case "raw":
		return known(comment.Text), true
	case "description":
		return known(strings.Join(comment.Description(), " ")), true
	case "tags":
		return known(tagRecords(comment.Comment.Tags)), true
	case "words":
		return known(texts(comment.Words())), true
	case "paragraphs":
		return known(float64(prose.Paragraphs(prose.WithoutFencedExamples(comment.Description()), func(line string) bool { return line != "" }))), true
	case "codeWords":
		return known(texts(words.documented.CodeWords())), true
	case "docsOnOwner":
		return known(float64(words.docs[words.documented])), true
	}

	return unknown, false
}
