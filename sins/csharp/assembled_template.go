package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// AssembledTemplate is `string.Join("\n", new[] { "public class X", "{", "}" })` or `sb.AppendLine("…")` line after line — a multi-line text built from line fragments, so its shape cannot be seen in the source.
type AssembledTemplate struct{}

func init() {
	sins.Register(catalog.CSharp, AssembledTemplate{})
}

// Definition is what the sin states about itself.
func (AssembledTemplate) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-assembled-template",
		Skill:       skills.Templates{},
		Description: "`string.Join(\"\\n\", new[] { \"public class X\", \"{\", \"}\" })` or `sb.AppendLine(\"…\")` line after line — a multi-line text built from line fragments, so its shape cannot be seen in the source",
		Rule:        "Write a multi-line text as a raw string literal that shows its output, not as lines joined with a newline.",
		Suggestion:  "Replace the join with `$\"\"\"` … `\"\"\"`, the lines written as they will appear and the values in `{placeholders}`.",
	}
}
