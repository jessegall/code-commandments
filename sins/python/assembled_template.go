package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// AssembledTemplate is a multi-line string built as a list of line fragments and `"\n".join(...)`-ed, instead of a triple-quoted f-string that shows its output.
type AssembledTemplate struct{}

func init() {
	sins.Register(catalog.Python, AssembledTemplate{})
}

// Definition is what the sin states about itself.
func (AssembledTemplate) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-assembled-template",
		Skill:       skills.Templates{},
		Description: "a multi-line string built as a list of line fragments and `\"\\n\".join(...)`-ed, instead of a triple-quoted f-string that shows its output",
		Rule:        "Write a multi-line string as one triple-quoted f-string (dedented) that shows its output, never a list of line fragments joined with a newline.",
		Suggestion:  "Replace the list and the join with `dedent(f\"\"\"…\"\"\")`, the varying parts as `{placeholders}` where they land.",
	}
}
