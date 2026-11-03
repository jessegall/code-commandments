package rule

import (
	"encoding/json"
	"maps"
	"reflect"
	"slices"
	"testing"
)

func TestTheSchemaDescribesEveryKeyARuleIsWrittenWith(t *testing.T) {
	var schema struct {
		Properties  map[string]any `json:"properties"`
		Definitions map[string]struct {
			Properties           map[string]map[string]any `json:"properties"`
			AdditionalProperties bool                      `json:"additionalProperties"`
		} `json:"definitions"`
	}

	if err := json.Unmarshal(Schema(), &schema); err != nil {
		t.Fatalf("the schema is no JSON: %v", err)
	}

	for shape, name := range shapes {
		definition := schema.Definitions[name]
		for _, key := range jsonKeys(shape) {
			if _, described := definition.Properties[key]; !described {
				t.Errorf("%s: the schema leaves %q out", name, key)
			}
		}

		if len(definition.Properties) != len(unique(jsonKeys(shape))) {
			t.Errorf("%s: the schema describes %d keys, the type has %d", name, len(definition.Properties), len(unique(jsonKeys(shape))))
		}
	}

	step := schema.Definitions["step"].Properties
	if step["nameLike"]["description"] == nil || step["inside"]["$ref"] != "#/definitions/step" || step["lines"]["$ref"] != "#/definitions/bounds" {
		t.Errorf("a step's checks are described and nested ones refer to their shapes: %v %v %v", step["nameLike"], step["inside"], step["lines"])
	}

	// A step that carries a key of its own refers to the shape that holds both it and the step nested in it.
	if step["descendant"]["$ref"] != "#/definitions/descended" || step["child"]["$ref"] != "#/definitions/childed" {
		t.Errorf("a bounded step refers to its own shape: %v %v", step["descendant"], step["child"])
	}

	if within := schema.Definitions["descended"].Properties["within"]; within["description"] == nil {
		t.Errorf("the schema says what a descendant's within means: %v", within)
	}

	if nesting := schema.Definitions["nesting"].Properties; nesting["count"]["type"] != "integer" {
		t.Errorf("nestedAtLeast counts with an integer: %v", nesting["count"])
	}

	for _, key := range []string{"$schema", "engine", "sin", "find"} {
		if schema.Properties[key] == nil {
			t.Errorf("the schema leaves the file's %q out", key)
		}
	}

	if !reflect.DeepEqual(Schema(), Schema()) {
		t.Error("the schema is written the same every time")
	}
}

func unique(keys []string) map[string]bool {
	set := map[string]bool{}
	for _, key := range keys {
		set[key] = true
	}

	return set
}

// A rule author's likeliest mistake is writing another language's tree: `InvocationExpression` under the php
// engine finds nothing, and nothing says why. The schema now carries one step per engine, with the value space
// that engine's languages really emit, so an editor completes the right kinds and marks the wrong ones where they
// are written.
func TestTheSchemaNarrowsAStepToTheEngineBeingJudged(t *testing.T) {
	var schema struct {
		AllOf       []map[string]any `json:"allOf"`
		Definitions map[string]struct {
			Properties map[string]map[string]any `json:"properties"`
		} `json:"definitions"`
	}

	if err := json.Unmarshal(Schema(), &schema); err != nil {
		t.Fatalf("the schema is no JSON: %v", err)
	}

	if len(schema.AllOf) != len(engineNames()) {
		t.Errorf("the schema holds %d engine rules, and there are %d engines", len(schema.AllOf), len(engineNames()))
	}

	php, held := schema.Definitions["step-php"]
	if !held {
		t.Fatalf("the schema has no step for the php engine: %v", slices.Sorted(maps.Keys(schema.Definitions)))
	}

	kinds := enumOf(t, php.Properties, "kind")
	if !slices.Contains(kinds, "Expr_StaticCall") {
		t.Errorf("the php step offers %d kinds and not Expr_StaticCall", len(kinds))
	}

	if slices.Contains(kinds, "InvocationExpression") {
		t.Error("the php step offers InvocationExpression, which is C#'s")
	}

	// The spelling a rule may write instead is offered beside the language's own word.
	if !slices.Contains(kinds, "static-call") {
		t.Error("the php step offers no spelling beside its kinds")
	}

	// And the other four keys a bridge spells are narrowed the same way.
	for key, want := range map[string]string{"field": "stmts", "operator": "??", "hasModifier": "readonly"} {
		if offered := enumOf(t, php.Properties, key); !slices.Contains(offered, want) {
			t.Errorf("the php step's %s offers %d values and not %q", key, len(offered), want)
		}
	}

	csharp, held := schema.Definitions["step-csharp"]
	if !held {
		t.Fatal("the schema has no step for the C# engine")
	}

	if offered := enumOf(t, csharp.Properties, "kind"); !slices.Contains(offered, "InvocationExpression") {
		t.Errorf("the C# step offers %d kinds and not InvocationExpression", len(offered))
	}
}

// enumOf is the values the key is narrowed to, and a failure where it is narrowed to none.
func enumOf(t *testing.T, properties map[string]map[string]any, key string) []string {
	t.Helper()

	// The values are offered inside an anyOf beside a plain string: an editor completes them, and a rule writing
	// something the census has not seen is not marked wrong for it.
	either, held := properties[key]["anyOf"].([]any)
	if !held || len(either) != 2 {
		t.Fatalf("the step's %s offers no values beside a plain string: %v", key, properties[key])
	}

	first, is := either[0].(map[string]any)
	if !is {
		t.Fatalf("the step's %s offers %v", key, either[0])
	}

	listed, held := first["enum"].([]any)
	if !held {
		t.Fatalf("the step's %s offers no enum: %v", key, first)
	}

	var values []string
	for _, value := range listed {
		if said, is := value.(string); is {
			values = append(values, said)
		}
	}

	return values
}
