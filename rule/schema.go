package rule

import (
	"encoding/json"
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

const SchemaFile = "rule.schema.json"

// object is one JSON Schema object.
type object = map[string]any

const expressionReference = "#/definitions/expression"

// Schema is the JSON Schema of a commandments:generic-rule/v2 file, for an editor to check a rule as it is written. The
// load-time checks go further: bound names, the roles a kind defines and the operands each operator takes.
func Schema() []byte {
	expression := object{"$ref": expressionReference}
	selectable := slices.Concat(contract.Kinds(), categoryNames())
	schema := object{
		"$schema":              "http://json-schema.org/draft-07/schema#",
		"title":                "A code-commandments rule",
		"description":          "A rule in the " + Format + " format: the engines it judges, the sin it names, the definitions it calls and the query over the generic tree that finds it.",
		"type":                 "object",
		"required":             []string{"$schema", "engine", "sin", "find"},
		"additionalProperties": false,
		"definitions": object{
			"expression": object{
				"description": "A list whose first item is an operator or a definition, applied to the rest; a string beginning with $ that refers to a bound name and the roles read through it; an object of a case's arms; or a plain value.",
				"anyOf": []object{
					{"type": "array", "minItems": 1, "items": []object{{"type": "string", "pattern": "^[^$]"}}, "additionalItems": expression},
					{"type": "object", "additionalProperties": expression},
					{"type": []string{"string", "number", "boolean", "null"}},
				},
			},
		},
		"properties": object{
			"$schema": object{"enum": []string{Format, FormerFormat}},
			"engine": object{"description": "The engine the rule judges, or the engines.", "anyOf": []object{
				{"enum": engineNames()},
				{"type": "array", "minItems": 1, "items": object{"enum": engineNames()}},
			}},
			"tags": object{
				"type":        "array",
				"items":       object{"type": "string", "minLength": 1},
				"description": "Words that group this rule with others, such as [\"testing\"]. judge --tag testing runs only the rules carrying one of the words named.",
			},
			"judges": object{
				"type":        "array",
				"items":       object{"type": "string"},
				"description": "Folders this rule judges although the project leaves them out of \"paths\" or names them under \"exclude\", relative to the project, such as [\"tests\"].",
			},
			"sin": object{
				"type":                 "object",
				"required":             []string{"name", "skill"},
				"additionalProperties": false,
				"properties": object{
					"name":        object{"type": "string", "description": "The sin's id, as a finding names it."},
					"aliases":     object{"type": "array", "items": object{"type": "string"}, "description": "Other names the sin answers to."},
					"skill":       object{"type": "string", "description": "The slug of the skill that teaches the fix, shipped or the project's own."},
					"description": object{"type": "string", "description": "The symptom, in one line."},
					"rule":        object{"type": "string", "description": "The positive directive the fix follows."},
					"suggestion":  object{"type": "string", "description": "What to do instead, in one line."},
				},
			},
			"definitions": object{
				"type":        "object",
				"description": "Named expressions the rule calls as it calls an operator: [\"name\", operands…].",
				"additionalProperties": object{
					"type":                 "object",
					"required":             []string{"args", "expr"},
					"additionalProperties": false,
					"properties": object{
						"args": object{"type": "array", "items": object{"type": "string"}, "description": "The names its operands are bound to, read inside expr as $name."},
						"expr": expression,
						"kept": object{"type": "boolean", "description": "Keep its answer for the whole judging by the values it is handed, for a definition many sites ask with the same arguments. One that reads the whole program is kept without it."},
					},
				},
			},
			"find": object{
				"type":                 "object",
				"oneOf":                []object{{"required": []string{"select", "as"}}, {"required": []string{"from", "pipeline"}}},
				"additionalProperties": false,
				"properties": object{
					"select": object{"description": "The kinds the rule judges: one kind or category of the tree, or a list of them.",
						"anyOf": []object{{"enum": selectable}, {"type": "array", "minItems": 1, "items": object{"enum": selectable}}}},
					"as":        object{"type": "string", "description": "The name each selected node is bound to, read as $name."},
					"where":     object{"$ref": expressionReference, "description": "A truth each selected node must hold to be judged further."},
					"let":       object{"type": "array", "description": "Ordered bindings [\"name\", expression]: each reads only the names bound before it.", "items": object{"type": "array", "minItems": 2, "maxItems": 2, "items": []object{{"type": "string"}, expression}}},
					"having":    object{"$ref": expressionReference, "description": "A truth over the bindings a node must hold to be a finding."},
					"emit":      object{"$ref": expressionReference, "description": "The node each finding reports at; the selected node when left out."},
					"from":      object{"type": "string", "description": "The relation whose rows the pipeline reads, in place of select."},
					"pipeline":  object{"type": "array", "description": "The stages a relation's rows go through, in order."},
					"relations": object{"type": "object", "description": "Relations the pipeline joins, built from others."},
				},
			},
		},
	}

	written, _ := json.MarshalIndent(schema, "", "    ")

	return append(written, '\n')
}

func categoryNames() []string {
	var names []string
	for _, name := range []string{"declaration", "statement", "expression", "type", "markup"} {
		if len(contract.Category(name)) > 0 {
			names = append(names, name)
		}
	}

	return names
}
