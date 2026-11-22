package make

import (
	"encoding/json"
	"strings"

	"github.com/jessegall/code-commandments/rule"
)

func SkillStub(b Blueprint) string {
	return "---\n" +
		"name: " + b.Sin + "\n" +
		"description: TODO — WHEN to reach for this skill: the code you are about to write that it governs.\n" +
		"summary: TODO — the discipline in one line, as the briefing lists it.\n" +
		"tier: keep-in-mind\n" +
		"languages: [" + extensions(b.Engines) + "]\n" +
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

func extensions(engines []Engine) string {
	var written []string
	for _, engine := range engines {
		written = append(written, ProbeExtension(engine))
	}

	return strings.Join(written, ", ")
}

func RuleStub(b Blueprint) string {
	var find any = map[string]any{
		"select": "call",
		"as":     "c",
		"where":  []any{"=", "$c.callee.name", "TODO-the-call-this-rule-flags"},
		"emit":   "$c",
	}
	description, directive := "TODO — the symptom, in one line.", "TODO — the positive directive the fix follows."

	if b.From != nil {
		find = b.From.Find
		description, directive = b.From.Sin.Description, b.From.Sin.Rule
	}

	var engines []string
	for _, engine := range b.Engines {
		engines = append(engines, string(engine))
	}

	rule := map[string]any{
		"$schema": rule.Format,
		"engine":  engines,
		"sin": map[string]any{
			"name":        b.ID,
			"description": description,
			"rule":        directive,
			"skill":       b.Slug,
		},
		"find": find,
	}

	var text strings.Builder

	encoder := json.NewEncoder(&text)
	encoder.SetEscapeHTML(false)
	encoder.SetIndent("", "    ")
	_ = encoder.Encode(rule)

	return text.String()
}
