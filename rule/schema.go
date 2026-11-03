package rule

import (
	"encoding/json"
	"reflect"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// SchemaFile is the name the schema is written under, beside the project's custom folder.
const SchemaFile = "rule.schema.json"

// object is one JSON Schema object.
type object = map[string]any

// shapes are the types a step holds, each a definition of the schema of its own, by the name it is defined under.
var shapes = map[reflect.Type]string{
	reflect.TypeFor[Step]():      "step",
	reflect.TypeFor[Bounds]():    "bounds",
	reflect.TypeFor[Count]():     "count",
	reflect.TypeFor[Tally]():     "tally",
	reflect.TypeFor[Argued]():    "argued",
	reflect.TypeFor[Childed]():   "childed",
	reflect.TypeFor[Descended](): "descended",
	reflect.TypeFor[Same]():      "same",
	reflect.TypeFor[Nesting]():   "nesting",
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
			"engine":  object{"enum": engineNames(), "description": "The engine the rule judges."},
			"judges": object{
				"type":        "array",
				"items":       object{"type": "string"},
				"description": "Folders this rule judges although the project leaves them out of \"paths\" or names them under \"exclude\", relative to the project, such as [\"tests\"]. They stay out of reach of every other rule. Writing it here rather than in the config keeps it with the rule, so it is not lost when the config is written again.",
			},
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
				"oneOf":                []object{{"required": []string{"select"}}, {"required": []string{"from"}}},
				"additionalProperties": false,
				"properties": object{
					"select": object{"type": "string", "description": "A neutral kind, \"comment\" to report on comments rather than code, or kind:<Kind> for a language's own.",
						"anyOf": []object{{"enum": append(neutralNames(), commentSelector)}, {"pattern": "^kind:.+$"}}},
					"from":   object{"type": "string", "description": "The rule whose findings this one narrows, in place of select: an inner detector."},
					"where":  object{"type": "array", "items": object{"$ref": "#/definitions/step"}, "description": "Every step must pass."},
					"reject": object{"type": "array", "items": object{"$ref": "#/definitions/step"}, "description": "Any step passing drops the node."},
				},
			},
			"fix": object{
				"type":                 "object",
				"description":          "The rewrite `repent` applies to each node the rule finds; leave it out and the sin is fixed by hand.",
				"oneOf":                []object{{"required": []string{"replace"}}, {"required": []string{"substitute"}}},
				"additionalProperties": false,
				"properties": object{
					"replace": object{"type": "string", "description": "The text written in place of the found node; each {target} stands for the source of the node an \"of\" target names, hops joined by /, such as {child:left} or {child:arguments/child:body}.",
						"examples": []string{"{child:left} > 0 && {child:right}"}},
					"substitute": object{
						"type":        "array",
						"description": "Regular-expression substitutions applied in order to the found node's source.",
						"items": object{
							"type":                 "object",
							"required":             []string{"matches", "with"},
							"additionalProperties": false,
							"properties": object{
								"matches": object{"type": "string", "description": "A Go regular expression."},
								"with":    object{"type": "string", "description": "What each match becomes; ${1} stands for a group."},
							},
						},
					},
				},
			},
		},
	}

	schema["allOf"] = perEngine(definitions)

	written, _ := json.MarshalIndent(schema, "", "    ")

	return append(written, '\n')
}

// perEngine is one rule per engine, each saying that a rule judging that engine writes its steps with the values
// that engine's languages really emit: an editor then completes the kinds of the language being judged and marks
// another language's kind as wrong where it is written, which is the likeliest mistake a rule author makes. The
// definitions gain one step shape per engine, which is the same step with the language's own value space on the
// five keys a bridge spells.
func perEngine(definitions object) []object {
	var rules []object
	for _, engine := range engineNames() {
		language := languages[catalog.Engine(engine)]
		if len(language) == 0 {
			continue
		}

		name := "step-" + engine
		definitions[name] = stepOf(language)
		rules = append(rules, object{
			"if": object{"properties": object{"engine": object{"const": engine}}, "required": []string{"engine"}},
			"then": object{"properties": object{"find": object{"properties": object{
				"where":  object{"items": object{"$ref": "#/definitions/" + name}},
				"reject": object{"items": object{"$ref": "#/definitions/" + name}},
			}}}},
		})
	}

	return rules
}

// stepOf is the step as those languages let it be written: every key as the step shape has it, and the five whose
// value a bridge spells narrowed to what the committed vocabularies list, kinds beside their spellings. A key
// stays as it was where nothing was recorded for the language, since an empty census must not refuse a rule for
// being ahead of it.
func stepOf(judged []contract.Language) object {
	step := shapeOf(reflect.TypeFor[Step]())
	properties, held := step["properties"].(object)
	if !held {
		return step
	}

	narrowed := object{}
	for key, property := range properties {
		narrowed[key] = property
	}

	for key, offered := range map[string][]string{"kind": append(values(judged, "kind"), spellings(judged)...),
		"field": values(judged, "field"), "operator": values(judged, "operator"),
		"hasFlag": values(judged, "hasFlag"), "hasModifier": values(judged, "hasModifier")} {
		if len(offered) == 0 {
			continue
		}

		held, is := narrowed[key].(object)
		if !is {
			continue
		}

		// OFFERED, not required: the census is observed, so a construct no fixture exercises is missing from it,
		// and an enum alone would mark a rule wrong for being ahead of the census. An editor completes the values
		// of the enum inside an anyOf and accepts any other string, which is exactly the honest claim — these are
		// the ones this language has been seen writing.
		listed := object{"anyOf": []object{{"enum": slices.Sorted(slices.Values(offered))}, {"type": "string"}}}
		for name, value := range held {
			if name != "type" {
				listed[name] = value
			}
		}
		narrowed[key] = listed
	}

	step["properties"] = narrowed

	return step
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
		if key == "value" {
			// A literal's own value, which is a scalar and not the bytes its field is held as.
			property = object{"type": valueScalars}
		}
		if key == "of" {
			property["description"] = "The node the step judges in place of the one it is on: parent, enclosingFunction, enclosingType, root, closest:<kind> or child:<field>."
		}

		if says, own := ownKeys[shape][key]; own {
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

// ownKeys are what a shape's own keys mean, where they are not the checks of the same name.
var ownKeys = map[reflect.Type]map[string]string{
	reflect.TypeFor[Count](): {
		"descendant": "count the descendants that pass this step",
		"child":      "count the children that pass this step",
		"field":      "count only the children filling this field",
	},
	reflect.TypeFor[Childed](): {
		"field": "step onto only the children filling this field of the tree",
	},
	reflect.TypeFor[Same](): {
		"of":     "the path from the node to the one it is compared with, any target \"of\" takes",
		"target": "one more step from there, such as \"child:iter\" for the collection a loop walks",
	},
	reflect.TypeFor[Descended](): {
		"within": "\"ownBody\" steps only onto the nodes the node holds itself, stopping at a function nested in it",
	},
}

// closedValues are the keys whose value is one of a few, and those few: the one list the parser checks a value
// against, the schema offers, and a slip of the keyboard is matched with.
var closedValues = map[string][]string{
	"is":         neutralNames(),
	"typeKind":   engine.TypeKinds,
	"typeShape":  contract.TypeShapes,
	"typeOrigin": contract.TypeOrigins,
	"role":       contract.Roles,
	"commentIs":  engine.Judgements,
	"nameCase":   {"camel", "pascal", "snake", "upper", "kebab"},
	"nameMood":   moods,
	"position":   {"first", "last", "only"},
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
