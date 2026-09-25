package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// ArchaeologyComment is a comment that tells the code's past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading.
type ArchaeologyComment struct{}

func init() {
	sins.Register(catalog.CSharp, ArchaeologyComment{})
}

// Definition is what the sin states about itself.
func (ArchaeologyComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-archaeology-comment",
		Skill:       skills.Documentation{},
		Description: "a comment that tells the code's past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading",
		Rule:        "Say what the code is now, never what it was; git keeps the history.",
		Suggestion:  "Delete the history. If something about the present needs saying, say that instead.",
	}
}
