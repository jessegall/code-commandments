package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

func commentsMatching(codebase *engine.Codebase, matches func(engine.Commented) bool) []engine.Match {
	var found []engine.Match
	seen := map[engine.Match]bool{}
	for _, comment := range codebase.CommentRuns() {
		if matches(comment) {
			location := comment.Location()
			if location.Exists() && !seen[location] {
				seen[location] = true
				found = append(found, location)
			}
		}
	}
	return found
}

// ArchaeologyComment judges comments that fail this documentation rule.
type ArchaeologyComment struct{}

func (ArchaeologyComment) Find(codebase *engine.Codebase) []engine.Match {
	return commentsMatching(codebase, engine.Commented.NarratesHistory)
}

func (ArchaeologyComment) Sin() Text {
	return Text{
		Name: "archaeology-comment", Skill: skills.Documentation{},
		Description: "A comment narrates the history of the code", Rule: "Explain why the code is written this way today.", Suggestion: "Move the history to the change record.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-archaeology-comment"},
			catalog.CSharp: {Sin: "csharp-archaeology-comment"},
		},
	}
}

// NegativeSpaceComment judges comments that fail this documentation rule.
type NegativeSpaceComment struct{}

func (NegativeSpaceComment) Find(codebase *engine.Codebase) []engine.Match {
	return commentsMatching(codebase, engine.Commented.DefendsAgainstStrawman)
}

func (NegativeSpaceComment) Sin() Text {
	return Text{
		Name: "negative-space-comment", Skill: skills.Documentation{},
		Description: "A comment defends the code against an assumption the reader has no reason to make", Rule: "Say what the code does.", Suggestion: "Keep a comment only when it explains a real constraint.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-negative-space-comment"},
			catalog.CSharp: {Sin: "csharp-negative-space-comment"},
		},
	}
}

// RestatedComment judges comments that fail this documentation rule.
type RestatedComment struct{}

func (RestatedComment) Find(codebase *engine.Codebase) []engine.Match {
	return commentsMatching(codebase, engine.Commented.RestatesStatement)
}

func (RestatedComment) Sin() Text {
	return Text{
		Name: "restated-comment", Skill: skills.Documentation{},
		Description: "A comment repeats the words of the statement it documents", Rule: "Give the statement clear names and let it speak for itself.", Suggestion: "Delete the comment or explain the reason for the statement.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-restated-comment"},
			catalog.CSharp: {Sin: "csharp-restated-comment"},
		},
	}
}

// BloatedDocblock judges comments that fail this documentation rule.
type BloatedDocblock struct{}

func (BloatedDocblock) Find(codebase *engine.Codebase) []engine.Match {
	return commentsMatching(codebase, engine.Commented.IsBloatedDoc)
}

func (BloatedDocblock) Sin() Text {
	return Text{
		Name: "bloated-docblock", Skill: skills.Documentation{},
		Description: "A type has several paragraphs of description", Rule: "Keep the type description short.", Suggestion: "Move supporting detail to the place where it is needed.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-bloated-docblock"},
			catalog.CSharp: {Sin: "csharp-bloated-docblock"},
		},
	}
}

// DanglingDocReference judges comments that fail this documentation rule.
type DanglingDocReference struct{}

func (DanglingDocReference) Find(codebase *engine.Codebase) []engine.Match {
	return commentsMatching(codebase, engine.Commented.Dangling)
}

func (DanglingDocReference) Sin() Text {
	return Text{
		Name: "dangling-doc-reference", Skill: skills.Documentation{},
		Description: "A document reference points at a declaration the project does not have", Rule: "Keep references tied to existing declarations.", Suggestion: "Correct the target or remove the reference.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-dangling-doc-reference"},
			catalog.CSharp: {Sin: "csharp-dangling-doc-reference"},
		},
	}
}

func (DanglingDocReference) CrossFile() {}

// CeremonyDocblock judges comments that fail this documentation rule.
type CeremonyDocblock struct{}

func (CeremonyDocblock) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.HasCeremonyDoc).Get()
}

func (CeremonyDocblock) Sin() Text {
	return Text{
		Name: "ceremony-docblock", Skill: skills.Documentation{},
		Description: "A doc comment repeats the function signature", Rule: "Keep documentation for information the signature does not already say.", Suggestion: "Remove the redundant tags and leave any useful explanation.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-ceremony-docblock"},
			catalog.CSharp: {Sin: "csharp-ceremony-docblock"},
		},
	}
}

// StackedDocblock judges comments that fail this documentation rule.
type StackedDocblock struct{}

func (StackedDocblock) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.StacksDocComments).Get()
}

func (StackedDocblock) Sin() Text {
	return Text{
		Name: "stacked-docblock", Skill: skills.Documentation{},
		Description: "Several doc comments describe one declaration", Rule: "Keep one doc comment per declaration.", Suggestion: "Merge useful text into one doc comment.",
	}
}
