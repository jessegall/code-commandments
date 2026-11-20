package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// UnnamedVocabularyLiteral finds a string passed where the code already uses a named constant.
type UnnamedVocabularyLiteral struct{}

func (UnnamedVocabularyLiteral) Find(codebase *engine.Codebase) []engine.Match {
	return engine.UnnamedVocabularyLiterals(codebase)
}

func (UnnamedVocabularyLiteral) Sin() Text {
	return Text{
		Name:        "unnamed-vocabulary-literal",
		Skill:       skills.Duplication{},
		Description: "A string literal fills a parameter that already has a named constant for that value",
		Rule:        "Use the vocabulary the parameter already has.",
		Suggestion:  "Replace the string with the named constant that carries the same value.",
		Formerly: map[catalog.Engine]Former{
			catalog.CSharp: {Sin: "csharp-unnamed-vocabulary-literal"},
			catalog.Python: {Sin: "python-unnamed-vocabulary-literal"},
		},
	}
}

func (UnnamedVocabularyLiteral) CrossFile() {}
