package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// AssembledTemplate is the assembled-template sin.
type AssembledTemplate struct{}

func init() { sins.Register(catalog.Backend, AssembledTemplate{}) }

// Definition is what the sin states about itself.
func (AssembledTemplate) Definition() sins.Definition {
	return sins.Definition{
		Name:        "assembled-template",
		Skill:       skills.Templates{},
		Description: `A multi-line template assembled as an array of line fragments and joined with a newline — the output is unreadable in the source that emits it`,
		Rule:        `State a fixed multi-line string as a heredoc, at its real indentation, and interpolate what varies — never as a list of line fragments joined by a newline.`,
		Suggestion:  "A heredoc (`<<<PHP` / `<<<'PHP'`), with the computed part as one interpolation.",
	}
}
