package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// UnnamedVocabularyLiteral is a raw string handed to a parameter the codebase elsewhere fills from a named constant — `expect("{")` beside `expect(Token.COLON)`, where `Token.BRACE_OPEN` already names it.
type UnnamedVocabularyLiteral struct{}

func init() {
	sins.Register(catalog.Python, UnnamedVocabularyLiteral{})
}

// Definition is what the sin states about itself.
func (UnnamedVocabularyLiteral) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-unnamed-vocabulary-literal",
		Skill:       skills.Enums{},
		Description: "a raw string handed to a parameter the codebase elsewhere fills from a named constant — `expect(\"{\")` beside `expect(Token.COLON)`, where `Token.BRACE_OPEN` already names it",
		Rule:        "Where a parameter is spelled from a named vocabulary, spell it that way everywhere — never the raw value at one call and the constant at the next.",
		Suggestion:  "The constant that already holds this value, referenced by name.",
	}
}
