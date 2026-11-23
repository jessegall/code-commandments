package rule

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// commentSource is what a rule selects to judge comments: each comment run of the program, as a record of its facts.
const commentSource = "comment"

// commentsOf is every comment run of the codebase as the record a rule reads: its prose, its form, the node it
// documents and that node's own words, whether its references dangle, and the node a finding about it reports at.
func commentsOf(codebase *engine.Codebase) []record {
	var reported []record
	for _, comment := range commentsOfPart(codebase) {
		if located, _ := nodeOf(comment["location"]); codebase.Reports(located.Source()) {
			reported = append(reported, comment)
		}
	}

	return reported
}

func commentsOfPart(codebase *engine.Codebase) []record {
	whole := codebase.Whole()
	every := everyComment(whole)
	if whole == codebase {
		return every
	}

	return engine.Analysis(codebase, "rule-comments-of-part", func(part *engine.Codebase) []record {
		held := map[*engine.File]bool{}
		for _, file := range part.Files() {
			held[file] = true
		}
		var comments []record
		for _, comment := range every {
			if located, _ := nodeOf(comment["location"]); held[located.Source()] {
				comments = append(comments, comment)
			}
		}

		return comments
	})
}

func everyComment(codebase *engine.Codebase) []record {
	return engine.Analysis(codebase, "rule-comments", func(codebase *engine.Codebase) []record {
		runs := codebase.CommentRuns()
		docs := map[engine.Match]int{}
		for _, comment := range runs {
			if comment.Form == "doc" {
				docs[comment.Documents()]++
			}
			if stacked := comment.StackedOn(); stacked.Exists() {
				docs[stacked]++
			}
		}
		comments := make([]record, 0, len(runs))
		for _, comment := range runs {
			documented := comment.Documents()
			comments = append(comments, record{
				"form":      known(comment.Form),
				"trailing":  known(comment.Trailing),
				"code":      known(comment.IsCode()),
				"documents": known(documented),
				"dangling":  known(comment.Dangling()),
				"line":      known(float64(comment.Line())),
				"location":  known(comment.Location()),
				derivedAt:   known(commentWords{comment: comment, documented: documented, docs: docs}),
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
