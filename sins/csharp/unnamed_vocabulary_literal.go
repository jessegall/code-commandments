package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// UnnamedVocabularyLiteral is a raw string handed to a parameter the codebase elsewhere fills from a named constant — `Expect("{")` beside `Expect(Token.Colon)`, where `Token.BraceOpen` already names it.
type UnnamedVocabularyLiteral struct{}

func init() {
	sins.Register(catalog.CSharp, UnnamedVocabularyLiteral{})
}

// Definition is what the sin states about itself.
func (UnnamedVocabularyLiteral) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-unnamed-vocabulary-literal",
		Skill:       skills.Enums{},
		Description: "a raw string handed to a parameter the codebase elsewhere fills from a named constant — `Expect(\"{\")` beside `Expect(Token.Colon)`, where `Token.BraceOpen` already names it",
		Rule:        "Where a parameter is spelled from a named vocabulary, spell it that way everywhere — never the raw value at one call and the constant at the next.",
		Suggestion:  "Replace the literal with the constant that names it (`Token.BraceOpen`); better still, make the vocabulary an enum and the parameter take it.",
	}
}
