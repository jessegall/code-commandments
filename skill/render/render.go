// Package render writes a skill as the tree of documents it is published as: the SKILL.md a reader loads to decide how to write the next line — principle, rules, one worked example, the commands that act on them — beside the reference/ documents holding what they want only afterwards.
package render

import (
	"bytes"
	"encoding/json"
	"github.com/jessegall/code-commandments/rules"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/library"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

const (
	reminder     = "> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one."
	reminderSelf = "> 🔱 **The rule above all — apply it ALWAYS.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This is that rule."
	fixAtSource  = "shared/fix-at-the-source"
	detectorsDoc = "detectors"
)

func Documents(teaching skill.Skill) map[string]string {
	definition := teaching.Definition()
	taught := sinsOf(definition.Slug)
	documents := map[string]string{"SKILL.md": body(definition, taught)}
	if len(taught) > 0 {
		documents[path(detectorsDoc)] = detectorsDocument(definition, taught)
	}
	for _, reference := range definition.References {
		documents[reference.Path()] = reference.Document()
	}

	return documents
}

func body(definition skill.Definition, taught []sins.Sin) string {
	opening := reminder
	if definition.Slug == fixAtSource {
		opening = reminderSelf
	}

	return written(
		frontmatter(definition),
		"# "+definition.Title,
		opening,
		strings.TrimSpace(definition.Body),
		commands(definition, taught),
		referenceIndex(definition, taught),
		related(definition),
	)
}

func written(blocks ...string) string {
	var kept []string
	for _, block := range blocks {
		if block != "" {
			kept = append(kept, block)
		}
	}

	return strings.Join(kept, "\n\n") + "\n"
}

func path(name string) string {
	return skill.Reference{Name: name}.Path()
}

func frontmatter(definition skill.Definition) string {
	var description bytes.Buffer
	encoder := json.NewEncoder(&description)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(definition.Trigger)

	return "---\nname: " + definition.ID() + "\ndescription: " + strings.TrimSuffix(description.String(), "\n") + "\n---"
}

func commands(definition skill.Definition, taught []sins.Sin) string {
	if len(taught) == 0 {
		return ""
	}
	var names, scaffolds []string
	bestDesign := false
	for _, sin := range taught {
		name := sin.Definition().Name
		names = append(names, name)
		finders := detectorsOf(sin)
		if scaffolding, helps := sin.(sins.Scaffolding); helps && len(scaffolding.Scaffolds()) > 0 {
			scaffolds = append(scaffolds, name)
		}
		if slices.ContainsFunc(finders, func(detector rule.Detector) bool {
			_, demands := detector.(rule.RequiresBestDesign)

			return demands
		}) {
			bestDesign = true
		}
	}
	rows := []string{
		"- `" + library.Binary + " judge --skill=" + definition.Slug + "` — find every one of these in the codebase.",
		"- `" + info("<sin>") + "` — what one rule flags, why it is a sin, and the fix. The sins here: " + code(names) + ".",
	}
	if len(scaffolds) > 0 {
		rows = append(rows, "- `"+library.Binary+" scaffold --sin=<sin>` — generate the helper the fix reaches for, for "+code(scaffolds)+".")
	}
	design := ""
	if bestDesign {
		design = ` --best-design="…"`
	}
	rows = append(rows, "- `"+library.Binary+` report --detector=<Detector> --reason="…"`+design+" --ref=path:line` — the flagged code is CORRECT "+
		"under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, "+
		"however far the fix cascades.")

	return "## Commands\n\n" + strings.Join(rows, "\n")
}

func info(sin string) string {
	return library.Binary + " info " + sin
}

func code(values []string) string {
	quoted := make([]string, len(values))
	for i, value := range values {
		quoted[i] = "`" + value + "`"
	}

	return strings.Join(quoted, ", ")
}

func referenceIndex(definition skill.Definition, taught []sins.Sin) string {
	var rows []string
	if len(taught) > 0 {
		rows = append(rows, "- [What fires, and why]("+path(detectorsDoc)+") — the symptom each detector flags, for when you are holding a finding.")
	}
	rows = append(rows, definition.ReferenceLinks()...)
	if len(rows) == 0 {
		return ""
	}

	return "## Reference\n\n" + strings.Join(rows, "\n")
}

func detectorsDocument(definition skill.Definition, taught []sins.Sin) string {
	var rows []string
	for _, sin := range taught {
		row := "- **`" + sin.Definition().Name + "`** — " + sin.Definition().Description
		if detector, found := detectorOf(sin); found {
			row += " — `" + catalog.Name(detector) + "`"
		}
		rows = append(rows, row)
	}

	return "# " + definition.Title + " — what fires, and why\n\n" +
		"Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `" +
		info("<sin>") + "` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.\n\n" +
		strings.Join(rows, "\n") + "\n"
}

func related(definition skill.Definition) string {
	if len(definition.Related) == 0 {
		return ""
	}
	var rows []string
	for _, relation := range definition.Related {
		rows = append(rows, "- [`"+relation.Slug+"`]("+relativeLink(definition.Slug, relation.Slug)+") — "+relation.Note)
	}

	return "## Related skills\n\n" + strings.Join(rows, "\n")
}

func relativeLink(from, to string) string {
	fromEngine, _, _ := strings.Cut(from, "/")
	toEngine, toName, _ := strings.Cut(to, "/")
	if fromEngine == toEngine {
		return "../" + toName + "/SKILL.md"
	}

	return "../../" + toEngine + "/" + toName + "/SKILL.md"
}

func sinsOf(slug string) []sins.Sin {
	var taught []sins.Sin
	for _, sin := range config.InClassOrder(config.Sin, rules.Sins()) {
		if sin.Definition().Slug() == slug {
			taught = append(taught, sin)
		}
	}

	return taught
}

func detectorOf(sin sins.Sin) (rule.Detector, bool) {
	finders := detectorsOf(sin)
	if len(finders) == 0 {
		return nil, false
	}

	return finders[0], true
}

func detectorsOf(sin sins.Sin) []rule.Detector {
	var finders []rule.Detector
	for _, detector := range rule.All() {
		if detector.Sin().Definition().Name == sin.Definition().Name {
			finders = append(finders, detector)
		}
	}

	return finders
}
