package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UnnamedVocabularyLiteral is the unnamed-vocabulary-literal sin.
type UnnamedVocabularyLiteral struct{}

func init() { sins.Register(catalog.Backend, UnnamedVocabularyLiteral{}) }

// Definition is what the sin states about itself.
func (UnnamedVocabularyLiteral) Definition() sins.Definition {
	return sins.Definition{
		Name:        "unnamed-vocabulary-literal",
		Skill:       skills.EnumsWithBehaviour{},
		Description: `A raw string in an argument the codebase elsewhere fills from a named vocabulary — ` + "`" + `expect('{')` + "`" + ` beside ` + "`" + `expect(Token::COLON)` + "`" + `, where ` + "`" + `Token::BRACE_OPEN` + "`" + ` already names it`,
		Rule:        `Where a parameter is spelled from a named vocabulary, spell it that way EVERYWHERE — never the raw value at one call site and the constant at the next.`,
		Suggestion:  "The constant that already holds this value, referenced by name.",
	}
}
