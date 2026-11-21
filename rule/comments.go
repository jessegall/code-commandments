package rule

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

// commentSource is what a rule selects to judge comments: each comment run of the program, as a record of its facts.
const commentSource = "comment"

// commentsOf is every comment run of the codebase as the record a rule reads: its prose, its form, the node it
// documents and that node's own words, whether its references dangle, and the node a finding about it reports at.
func commentsOf(codebase *engine.Codebase) []record {
	return engine.Analysis(codebase, "rule-comments", func(codebase *engine.Codebase) []record {
		runs := codebase.CommentRuns()
		docs := map[engine.Match]int{}
		for _, comment := range runs {
			if comment.Form == "doc" {
				docs[comment.Documents()]++
			}
		}
		comments := make([]record, 0, len(runs))
		for _, comment := range runs {
			documented := comment.Documents()
			description := strings.Join(comment.Description(), " ")
			comments = append(comments, record{
				"text":        known(comment.Prose()),
				"raw":         known(comment.Text),
				"description": known(description),
				"tags":        known(tagRecords(comment.Comment.Tags)),
				"words":       known(texts(comment.Words())),
				"paragraphs":  known(float64(prose.Paragraphs(prose.WithoutFencedExamples(comment.Description()), func(line string) bool { return line != "" }))),
				"form":        known(comment.Form),
				"trailing":    known(comment.Trailing),
				"code":        known(comment.IsCode()),
				"documents":   known(documented),
				"codeWords":   known(texts(documented.CodeWords())),
				"docsOnOwner": known(float64(docs[documented])),
				"dangling":    known(comment.Dangling()),
				"line":        known(float64(comment.Line())),
				"location":    known(comment.Location()),
			})
		}

		return comments
	})
}

// commentRecords is the comment runs a node holds or documents: those above it and those inside it.
func commentRecords(site engine.Match) []record {
	var found []record
	for _, comment := range commentsOf(site.Codebase()) {
		documented, _ := nodeOf(comment["documents"])
		located, _ := nodeOf(comment["location"])
		if (documented.Exists() && documented.Node() == site.Node()) || (located.Exists() && located.Source() == site.Source() && isUnder(located, site)) {
			found = append(found, comment)
		}
	}

	return found
}

// tagRecords are a doc comment's tags as records: name, text, type and subject, each empty text when the tag has none.
func tagRecords(tags []contract.DocTag) []value {
	held := make([]value, len(tags))
	for at, tag := range tags {
		held[at] = known(record{"name": known(tag.Name), "text": known(tag.Text), "type": known(tag.Type), "subject": known(tag.Subject)})
	}

	return held
}

func texts(words []string) []value {
	held := make([]value, len(words))
	for at, word := range words {
		held[at] = known(word)
	}

	return held
}
