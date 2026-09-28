package rule

import (
	"encoding/json"
	"reflect"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// SchemaFile is the name the schema is written under, beside the project's custom folder.
const SchemaFile = "rule.schema.json"

// object is one JSON Schema object.
type object = map[string]any

// shapes are the types a step holds, each a definition of the schema of its own, by the name it is defined under.
var shapes = map[reflect.Type]string{
	reflect.TypeFor[Step]():    "step",
	reflect.TypeFor[Bounds]():  "bounds",
	reflect.TypeFor[Count]():   "count",
	reflect.TypeFor[Tally]():   "tally",
	reflect.TypeFor[Argued]():  "argued",
	reflect.TypeFor[Nesting](): "nesting",
}

// Schema is the JSON Schema of a rule file, read from the types a rule is parsed into so it cannot drift from
// them, each check described as the catalog describes it.
func Schema() []byte {
	definitions := object{}
	for shape, name := range shapes {
		definitions[name] = shapeOf(shape)
	}

	schema := object{
		"$schema":              "http://json-schema.org/draft-07/schema#",
		"title":                "A code-commandments rule",
		"description":          "A commandment of the project's own: the engine it judges, the sin it names and the query that finds it.",
		"type":                 "object",
		"required":             []string{"engine", "sin", "find"},
		"additionalProperties": false,
		"definitions":          definitions,
		"properties": object{
			"$schema": object{"type": "string"},
			"engine":  object{"enum": []string{"backend", "frontend", "typescript", "python", "csharp"}, "description": "The engine the rule judges."},
			"sin": object{
				"type":                 "object",
				"required":             []string{"name", "skill"},
				"additionalProperties": false,
				"properties": object{
					"name":        object{"type": "string", "description": "The sin's id, as a finding names it."},
					"description": object{"type": "string", "description": "The symptom, in one line."},
					"rule":        object{"type": "string", "description": "The positive directive the fix follows."},
					"suggestion":  object{"type": "string", "description": "What to do instead, in one line."},
					"skill":       object{"type": "string", "description": "The slug of the skill that teaches the fix, shipped or the project's own."},
				},
			},
			"find": object{
				"type":                 "object",
				"required":             []string{"select"},
				"additionalProperties": false,
				"properties": object{
					"select": object{"type": "string", "description": "A neutral kind, or kind:<Kind> for a language's own.",
						"anyOf": []object{{"enum": neutralNames()}, {"pattern": "^kind:.+$"}}},
					"where":  object{"type": "array", "items": object{"$ref": "#/definitions/step"}, "description": "Every step must pass."},
					"reject": object{"type": "array", "items": object{"$ref": "#/definitions/step"}, "description": "Any step passing drops the node."},
				},
			},
		},
	}

	written, _ := json.MarshalIndent(schema, "", "    ")

	return append(written, '\n')
}

// shapeOf is the schema of a type a step holds: its fields, an embedded type's among them, and nothing else.
func shapeOf(shape reflect.Type) object {
	properties := object{}
	fieldsOf(shape, properties)

	return object{"type": "object", "additionalProperties": false, "properties": properties}
}

// fieldsOf adds the schema of each of the type's written fields.
func fieldsOf(shape reflect.Type, properties object) {
	for i := range shape.NumField() {
		field := shape.Field(i)
		if field.Anonymous {
			fieldsOf(field.Type, properties)

			continue
		}

		key, _, _ := strings.Cut(field.Tag.Get("json"), ",")
		if key == "" || key == "-" || !field.IsExported() {
			continue
		}

		property := typeOf(field.Type)
		if key == "of" {
			property["description"] = "The node the step judges in place of the one it is on: parent, enclosingFunction, enclosingType, root, closest:<kind> or child:<field>."
		}

		if says, counting := countKeys[key]; counting && shape == reflect.TypeFor[Count]() {
			property["description"] = says
		} else if check, found := checkNamed(key); found && field.Type.Kind() != reflect.Int {
			property["description"] = check.Keeps
			property["examples"] = []json.RawMessage{json.RawMessage(check.Example)}
		}

		if values, closed := closedValues[key]; closed {
			property["enum"] = values
		}

		properties[key] = property
	}
}

// typeOf is the schema of a field's type.
func typeOf(written reflect.Type) object {
	if written.Kind() == reflect.Pointer {
		written = written.Elem()
	}

	if name, shaped := shapes[written]; shaped {
		return object{"$ref": "#/definitions/" + name}
	}

	switch written.Kind() {
	case reflect.Bool:
		return object{"type": "boolean"}
	case reflect.Int:
		return object{"type": "integer"}
	case reflect.Slice:
		return object{"type": "array", "items": typeOf(written.Elem())}
	default:
		return object{"type": "string"}
	}
}

// countKeys are what a count's own keys mean, which are not the checks of the same name.
var countKeys = map[string]string{
	"descendant": "count the descendants that pass this step",
	"child":      "count the children that pass this step",
	"field":      "count only the children filling this field",
}

// closedValues are the keys whose value is one of a few.
var closedValues = map[string]any{
	"is":       neutralNames(),
	"typeKind": engine.TypeKinds,
	"nameCase": []string{"camel", "pascal", "snake", "upper", "kebab"},
	"position": []string{"first", "last", "only"},
}

// checkNamed is the catalog's check of the key.
func checkNamed(key string) (Check, bool) {
	for _, group := range Checks {
		for _, check := range group.Checks {
			if check.Key == key {
				return check, true
			}
		}
	}

	return Check{}, false
}
