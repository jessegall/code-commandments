package rule

import (
	"encoding/json"
	"reflect"
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
	if step["nameLike"]["description"] == nil || step["descendant"]["$ref"] != "#/definitions/step" || step["lines"]["$ref"] != "#/definitions/bounds" {
		t.Errorf("a step's checks are described and nested ones refer to their shapes: %v %v %v", step["nameLike"], step["descendant"], step["lines"])
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
