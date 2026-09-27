package config

import (
	"bytes"
	"encoding/json"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

// object is a JSON Schema node, written in the order its keys are set.
type object = map[string]any

// ownRule is the name a project's own rule is given: its class's short name.
const ownRule = `^[A-Za-z_][A-Za-z0-9_]*$`

// Schema is the JSON Schema of config.json: every key described, and every shipped rule, language and
// hook offered by name, so an editor completes a config and explains it in place of comments.
func Schema() []byte {
	schema := object{
		"$schema":              "https://json-schema.org/draft/2020-12/schema",
		"title":                "code-commandments project config",
		"description":          "How `commandments judge` reads this project. Every key is optional; `commandments config` shows what runs.",
		"type":                 "object",
		"additionalProperties": false,
		"properties": object{
			"$schema": object{"type": "string", "description": "The schema this file is checked against, written by `commandments sync`."},
			"paths":   list("The source roots judge scans when it is given no path, relative to the project.", object{"type": "string"}),
			"exclude": list("Paths never reported on nor rewritten; they are still parsed, so findings elsewhere stay correct.", object{"type": "string"}),
			"disable": object{
				"description":          "Rules and languages turned off. A detector is off when it, its sin or its skill is named here.",
				"type":                 "object",
				"additionalProperties": false,
				"properties": object{
					"languages": list("Languages the project does not write; judge never reads them.", languages()),
					"skills":    list("Whole skills turned off, every sin under them with it.", rules(skillRules())),
					"sins":      list("Sins turned off, whichever detector finds them.", rules(sinRules())),
					"detectors": list("Detectors turned off, shipped (engine/Name) or the project's own (Name).", rules(detectorRules())),
					"hooks":     list("Hooks turned off, by name.", object{"type": "string", "pattern": ownRule}),
					"agents":    list("Agents turned off, by name: ClaudeAgent or CodexAgent.", object{"type": "string", "pattern": ownRule}),
				},
			},
			"detectors": list("The project's own detectors in .commandments/custom/, by name, turned on.", object{"type": "string", "pattern": ownRule}),
			"packages":  list("The project's own exemption packages in .commandments/custom/, by name.", object{"type": "string", "pattern": ownRule}),
			"hooks":     list("Hooks turned on beyond the ones every project runs, by name.", object{"type": "string"}),
			"agents":    list("Agents a config from the PHP tool turned on by name. Every project runs both agents the tool ships and it ships no other, so each name here is skipped, and said so.", object{"type": "string"}),
			"configure": object{
				"description":   "Shipped detectors tuned, keyed by engine/Name. Each step calls one of the detector's methods with its arguments in order, as {\"layer\": [\"App\\\\Http\", [\"App\\\\Domain\"]]}.",
				"type":          "object",
				"propertyNames": rules(detectorRules()),
				"additionalProperties": list("The calls, in order.", object{
					"type":          "object",
					"minProperties": 1,
					"maxProperties": 1,
					"additionalProperties": object{
						"type":  "array",
						"items": object{"$ref": "#/$defs/literal"},
					},
				}),
			},
		},
		"$defs": object{
			"literal": object{
				"description": "A string, a whole number, true, false, null, or a list of those.",
				"anyOf": []any{
					object{"type": []string{"string", "integer", "boolean", "null"}},
					object{"type": "array", "items": object{"$ref": "#/$defs/literal"}},
				},
			},
		},
	}

	var out bytes.Buffer

	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	encoder.SetIndent("", "    ")
	_ = encoder.Encode(schema)

	return out.Bytes()
}

func list(description string, items object) object {
	return object{"description": description, "type": "array", "uniqueItems": true, "items": items}
}

// named is one rule offered by name, with what it is for.
type named struct {
	id          string
	description string
}

// rules offers the shipped rules by name, and any name of the project's own.
func rules(shipped []named) object {
	choices := []any{}

	for _, rule := range shipped {
		choices = append(choices, object{"const": rule.id, "description": rule.description})
	}

	return object{"type": "string", "anyOf": append(choices, object{"pattern": ownRule, "description": "A rule of the project's own, by name."})}
}

func languages() object {
	var names []any

	for _, language := range source.Languages {
		names = append(names, LanguageName(language))
	}

	return object{"type": "string", "enum": names}
}

func skillRules() []named {
	var rules []named

	for _, engine := range catalog.Engines {
		for _, each := range skill.Of(engine) {
			rules = append(rules, named{Rule{Skill, engine, catalog.Name(each)}.ID(), each.Definition().Summary})
		}
	}

	return rules
}

func sinRules() []named {
	var rules []named

	for _, engine := range catalog.Engines {
		for _, each := range sins.Of(engine) {
			rules = append(rules, named{Rule{Sin, engine, catalog.Name(each)}.ID(), each.Definition().Description})
		}
	}

	return rules
}

func detectorRules() []named {
	var rules []named

	for _, engine := range catalog.Engines {
		for _, each := range detectors.Of(engine) {
			rules = append(rules, named{Rule{Detector, engine, catalog.Name(each)}.ID(), "Finds " + each.Sin().Definition().Name + "."})
		}
	}

	return rules
}
