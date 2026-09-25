package config

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

func TestTheSchemaOffersEveryShippedDetectorByName(t *testing.T) {
	var schema map[string]any
	if err := json.Unmarshal(Schema(), &schema); err != nil {
		t.Fatal(err)
	}

	disable := schema["properties"].(map[string]any)["disable"].(map[string]any)["properties"].(map[string]any)
	offered := map[string]bool{}

	for _, choice := range disable["detectors"].(map[string]any)["items"].(map[string]any)["anyOf"].([]any) {
		if id, named := choice.(map[string]any)["const"].(string); named {
			offered[id] = true
		}
	}

	for _, detector := range detectors.All() {
		engine, _ := detectors.EngineOf(detector)

		if id := (Rule{Detector, engine, catalog.Name(detector)}).ID(); !offered[id] {
			t.Errorf("%s is not offered", id)
		}
	}

	if !strings.Contains(string(Schema()), `"const": "backend/ArrayBagDetector"`) {
		t.Error("the schema does not name backend/ArrayBagDetector")
	}
}
