package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// MatchDefaultReturnsNull is a `switch` that names every member of an enum, then answers `null`, `default` or `false` in its `_` arm — the one value that arm can see is a bug, and it is answered as if it were fine.
type MatchDefaultReturnsNull struct{}

func init() {
	sins.Register(catalog.CSharp, MatchDefaultReturnsNull{})
}

// Definition is what the sin states about itself.
func (MatchDefaultReturnsNull) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-match-default-returns-null",
		Skill:       skills.Enums{},
		Description: "a `switch` that names every member of an enum, then answers `null`, `default` or `false` in its `_` arm — the one value that arm can see is a bug, and it is answered as if it were fine",
		Rule:        "When a switch names every member of an enum, make its `_` arm throw.",
		Suggestion:  "Write `_ => throw new ArgumentOutOfRangeException(nameof(status), status, null)`.",
	}
}
