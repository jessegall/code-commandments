package make

import (
	"encoding/json"
	"strings"

	"github.com/jessegall/code-commandments/cli/custom"
)

// SkillStub is a new skill's SKILL.md: its front matter, and the sections a finding sends a reader to.
func SkillStub(b Blueprint) string {
	return "---\n" +
		"name: " + b.Sin + "\n" +
		"description: TODO — WHEN to reach for this skill: the code you are about to write that it governs.\n" +
		"summary: TODO — the discipline in one line, as the briefing lists it.\n" +
		"tier: keep-in-mind\n" +
		"languages: [" + b.Engine.ProbeExtension() + "]\n" +
		"---\n\n" +
		"# " + b.Sin + "\n\n" +
		"TODO — what good looks like here, and why.\n\n" +
		"## When it fires\n\n" +
		"- **`" + b.ID + "`** — TODO: the symptom, as a reader would spot it.\n\n" +
		"## How to fix it\n\n" +
		"TODO — the fix at the source, not the silencing of the symptom.\n\n" +
		"## Example\n\n" +
		"TODO — the code as it is flagged, then as it should be.\n"
}

// RuleStub is the rule: its engine, its sin, and a query that finds nothing until it is written.
func RuleStub(b Blueprint) string {
	rule := map[string]any{
		"$schema": custom.SchemaReference,
		"engine":  string(b.Engine),
		"sin": map[string]any{
			"name":        b.ID,
			"description": "TODO — the symptom, in one line.",
			"rule":        "TODO — the positive directive the fix follows.",
			"skill":       b.Slug,
		},
		"find": map[string]any{
			"select": "call",
			"where":  []any{map[string]any{"name": "TODO-the-call-this-rule-flags"}},
			"reject": []any{},
		},
	}

	var text strings.Builder

	encoder := json.NewEncoder(&text)
	encoder.SetEscapeHTML(false)
	encoder.SetIndent("", "    ")
	_ = encoder.Encode(rule)

	return text.String()
}
