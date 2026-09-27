// Package render writes a skill as the tree of documents it is published as: the SKILL.md a reader loads to decide
// how to write the next line — principle, rules, one worked example, the commands that act on them — beside the
// reference/ documents holding what they want only afterwards. All of it is a projection of the skill and its sins,
// with no count written down, so the documents cannot drift from the detectors.
package render

import (
	"bytes"
	"encoding/json"
	"fmt"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

const (
	reminder     = "> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one."
	reminderSelf = "> 🔱 **The rule above all — apply it ALWAYS.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This is that rule."
	fixAtSource  = "backend/fix-at-the-source"
	examplesDoc  = "examples"
	detectorsDoc = "detectors"
)

// Example is one worked example: the language it is written in, and its two halves, nil where the fixture has none.
type Example struct {
	Language source.Language `json:"language"`
	Bad      *string         `json:"bad"`
	Good     *string         `json:"good"`
}

// Examples are the worked examples of each detector, by its Key.
type Examples map[string][]Example

// Key is how Examples name a detector: its sin's skill and its name, which alone repeats across engines.
func Key(detector detectors.Detector) string {
	return detector.Sin().Definition().Slug() + ":" + catalog.Name(detector)
}

// worked is one example with every sin it demonstrates.
type worked struct {
	example Example
	sins    []sins.Sin
}

// Documents is every file the skill publishes, by its path relative to the skill's folder.
func Documents(teaching skill.Skill, examples Examples) map[string]string {
	definition := teaching.Definition()
	taught := sinsOf(definition.Slug)
	examplesOf := workedExamples(taught, examples)
	documents := map[string]string{"SKILL.md": body(definition, taught, examplesOf)}
	if len(examplesOf) > 1 {
		documents[path(examplesDoc)] = examplesDocument(definition, examplesOf)
	}
	if len(taught) > 0 {
		documents[path(detectorsDoc)] = detectorsDocument(definition, taught)
	}
	for _, reference := range definition.References {
		documents[path(reference.Name)] = "# " + reference.Title + "\n\n" + strings.TrimSpace(reference.Body) + "\n"
	}

	return documents
}

// body is the SKILL.md itself: what a reader has in front of them while writing the line.
func body(definition skill.Definition, taught []sins.Sin, examplesOf []worked) string {
	opening := reminder
	if definition.Slug == fixAtSource {
		opening = reminderSelf
	}
	blocks := []string{
		frontmatter(definition),
		"# " + definition.Title,
		opening,
		blockquote(definition.Intro),
		"## The principle\n\n" + strings.TrimSpace(definition.Principle),
		rules(taught),
		workedExample(examplesOf),
		commands(definition, taught),
		referenceIndex(definition, taught, examplesOf),
		related(definition),
	}
	var kept []string
	for _, block := range blocks {
		if block != "" {
			kept = append(kept, block)
		}
	}

	return strings.Join(kept, "\n\n") + "\n"
}

func path(name string) string {
	return "reference/" + name + ".md"
}

// banner is the rule this half of an example shows, as a divider inside the fence.
func banner(half string) string {
	return "----------[ " + half + " ]----------"
}

// frontmatter names the skill by its id and quotes its trigger as JSON, which is exactly the escape a YAML
// double-quoted scalar wants: a trigger holding ": " or " #" is not plain YAML.
func frontmatter(definition skill.Definition) string {
	var description bytes.Buffer
	encoder := json.NewEncoder(&description)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(definition.Trigger)

	return "---\nname: " + definition.ID() + "\ndescription: " + strings.TrimSuffix(description.String(), "\n") + "\n---"
}

func blockquote(text string) string {
	lines := strings.Split(text, "\n")
	for i, line := range lines {
		lines[i] = "> " + line
	}

	return strings.Join(lines, "\n")
}

// rules is one directive per sin, a checkbox so the one list serves writing and reviewing alike.
func rules(taught []sins.Sin) string {
	var rows []string
	for _, sin := range taught {
		definition := sin.Definition()
		row := "- [ ] " + definition.Rule
		if definition.Suggestion != "" {
			row += "\n      _" + definition.Suggestion + "_"
		}
		rows = append(rows, row)
	}
	if len(rows) == 0 {
		return ""
	}

	return "## Rules\n\n" + strings.Join(rows, "\n")
}

// commands is what a reader can run on the skill's rules, spelled as every report spells them.
func commands(definition skill.Definition, taught []sins.Sin) string {
	if len(taught) == 0 {
		return ""
	}
	var names, fixable, scaffolds []string
	bestDesign := false
	for _, sin := range taught {
		name := sin.Definition().Name
		names = append(names, name)
		detector, _ := detectorOf(sin)
		if _, repents := detector.(detectors.Repentable); repents {
			fixable = append(fixable, name)
		}
		if scaffolding, helps := sin.(sins.Scaffolding); helps && len(scaffolding.Scaffolds()) > 0 {
			scaffolds = append(scaffolds, name)
		}
		if _, demands := detector.(detectors.RequiresBestDesign); demands {
			bestDesign = true
		}
	}
	rows := []string{
		"- `" + binary.Shim + " judge --skill=" + definition.Slug + "` — find every one of these in the codebase.",
		"- `" + info("<sin>") + "` — what one rule flags, why it is a sin, and the fix. The sins here: " + code(names) + ".",
	}
	if len(fixable) > 0 {
		rows = append(rows, "- `"+binary.Shim+" repent --sin=<sin>` — auto-fix, for "+code(fixable)+". Review it with `--dry-run` first.")
	}
	if len(scaffolds) > 0 {
		rows = append(rows, "- `"+binary.Shim+" scaffold --sin=<sin>` — generate the helper the fix reaches for, for "+code(scaffolds)+".")
	}
	design := ""
	if bestDesign {
		design = ` --best-design="…"`
	}
	rows = append(rows, "- `"+binary.Shim+` report --detector=<Detector> --reason="…"`+design+" --ref=path:line` — the flagged code is CORRECT "+
		"under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, "+
		"however far the fix cascades.")

	return "## Commands\n\n" + strings.Join(rows, "\n")
}

func info(sin string) string {
	return binary.Shim + " info " + sin
}

func code(values []string) string {
	quoted := make([]string, len(values))
	for i, value := range values {
		quoted[i] = "`" + value + "`"
	}

	return strings.Join(quoted, ", ")
}

// referenceIndex lists the documents the skill ships beside its teaching, each with the question it answers.
func referenceIndex(definition skill.Definition, taught []sins.Sin, examplesOf []worked) string {
	var rows []string
	if len(examplesOf) > 1 {
		rows = append(rows, fmt.Sprintf("- [Worked examples](%s) — every rule's bad → good, %d of them.", path(examplesDoc), len(examplesOf)))
	}
	if len(taught) > 0 {
		rows = append(rows, "- [What fires, and why]("+path(detectorsDoc)+") — the symptom each detector flags, for when you are holding a finding.")
	}
	for _, reference := range definition.References {
		rows = append(rows, "- ["+reference.Title+"]("+path(reference.Name)+")")
	}
	if len(rows) == 0 {
		return ""
	}

	return "## Reference\n\n" + strings.Join(rows, "\n")
}

// workedExample is the one example the body carries, the first rule's; the rest live in reference/examples.md.
func workedExample(examplesOf []worked) string {
	if len(examplesOf) == 0 {
		return ""
	}
	rendered := example(examplesOf[0], len(languagesOf(examplesOf)) > 1)
	if rendered == "" {
		return ""
	}
	block := "## Worked example\n\n" + rendered
	if len(examplesOf) > 1 {
		block += fmt.Sprintf("\n\nThe other %d — one per rule — are in [`%s`](%s).", len(examplesOf)-1, path(examplesDoc), path(examplesDoc))
	}

	return block
}

// examplesDocument is every worked example, the body's included, so it stands on its own.
func examplesDocument(definition skill.Definition, examplesOf []worked) string {
	named := len(languagesOf(examplesOf)) > 1
	var blocks []string
	for _, group := range examplesOf {
		if block := example(group, named); block != "" {
			blocks = append(blocks, block)
		}
	}

	return "# " + definition.Title + " — worked examples\n\n" +
		"One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code " +
		"that really fires and really passes.\n\n" + strings.Join(blocks, "\n\n") + "\n"
}

// detectorsDocument maps each symptom to the detector that flags it, for a reader holding a finding.
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

// workedExamples is every example of the skill's sins, one per bad and good pair, so a sinful method carrying
// several sins shows once, headed by every sin it demonstrates.
func workedExamples(taught []sins.Sin, examples Examples) []worked {
	var grouped []worked
	index := map[string]int{}
	for _, sin := range taught {
		detector, found := detectorOf(sin)
		if !found {
			continue
		}
		for _, each := range examples[Key(detector)] {
			key := text(each.Bad) + "\x00" + text(each.Good)
			if text(each.Bad) == "" {
				key += "\x00" + sin.Definition().Name
			}
			at, seen := index[key]
			if !seen {
				at = len(grouped)
				index[key] = at
				grouped = append(grouped, worked{})
			}
			grouped[at].example = each
			grouped[at].sins = append(grouped[at].sins, sin)
		}
	}

	return grouped
}

func text(half *string) string {
	if half == nil {
		return ""
	}

	return *half
}

// languagesOf is the distinct languages the examples are written in.
func languagesOf(examplesOf []worked) []source.Language {
	var languages []source.Language
	for _, group := range examplesOf {
		if !contains(languages, group.example.Language) {
			languages = append(languages, group.example.Language)
		}
	}

	return languages
}

func contains(languages []source.Language, language source.Language) bool {
	for _, each := range languages {
		if each == language {
			return true
		}
	}

	return false
}

// example is one before and after, headed by the sins it demonstrates and their symptoms; named says the skill
// teaches more than one language, so the heading says which this is.
func example(group worked, named bool) string {
	var parts []string
	if group.example.Bad != nil {
		parts = append(parts, banner("Bad")+"\n\n"+*group.example.Bad)
	}
	if group.example.Good != nil {
		parts = append(parts, banner("Good")+"\n\n"+*group.example.Good)
	}
	if len(parts) == 0 || len(group.sins) == 0 {
		return ""
	}
	var names, symptoms []string
	for _, sin := range group.sins {
		names = append(names, sin.Definition().Name)
		symptoms = append(symptoms, "_"+sin.Definition().Name+"_ — "+sin.Definition().Description)
	}
	if len(group.sins) == 1 {
		symptoms = []string{group.sins[0].Definition().Description}
	}
	heading := "### " + strings.Join(names, " · ")
	if named {
		heading += " — in " + group.example.Language.Label()
	}

	return heading + "\n\n" + strings.Join(symptoms, "\n\n") + "\n\n```" + string(group.example.Language) + "\n" + strings.Join(parts, "\n\n") + "\n```"
}

// related links each related skill by a path made from its slug, with the note that says why.
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

// sinsOf is every published sin the skill teaches, in the order the PHP catalogs list them.
func sinsOf(slug string) []sins.Sin {
	var taught []sins.Sin
	for _, sin := range config.InClassOrder(config.Sin, sins.All()) {
		if sin.Definition().Slug() == slug {
			taught = append(taught, sin)
		}
	}

	return taught
}

// detectorOf is the published detector that finds the sin.
func detectorOf(sin sins.Sin) (detectors.Detector, bool) {
	for _, detector := range detectors.All() {
		if detector.Sin() == sin {
			return detector, true
		}
	}

	return nil, false
}
