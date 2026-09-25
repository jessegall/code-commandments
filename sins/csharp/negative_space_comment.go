package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// NegativeSpaceComment is a comment defending the code against a reading nobody made — `// not magic, just a day`, `// deliberately not sorted` — saying what it is not instead of what it is.
type NegativeSpaceComment struct{}

func init() {
	sins.Register(catalog.CSharp, NegativeSpaceComment{})
}

// Definition is what the sin states about itself.
func (NegativeSpaceComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-negative-space-comment",
		Skill:       skills.Documentation{},
		Description: "a comment defending the code against a reading nobody made — `// not magic, just a day`, `// deliberately not sorted` — saying what it is not instead of what it is",
		Rule:        "Say what the code is; a comment answering an objection nobody raised means the code should make itself plain.",
		Suggestion:  "Name the thing so it explains itself (a named constant, a well-named method) and delete the comment, or say what it is instead.",
	}
}
