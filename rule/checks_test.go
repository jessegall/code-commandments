package rule

import (
	"bytes"
	"encoding/json"
	"reflect"
	"slices"
	"testing"
)

func TestTheCatalogNamesEveryCheckAStepCanMake(t *testing.T) {
	var fields []string
	for _, key := range jsonKeys(reflect.TypeFor[Step]()) {
		if key != "of" {
			fields = append(fields, key)
		}
	}

	for _, key := range fields {
		if !slices.Contains(keys(), key) {
			t.Errorf("the step checks %q, which the catalog does not name", key)
		}
	}

	for _, key := range keys() {
		if !slices.Contains(fields, key) {
			t.Errorf("the catalog names %q, which no step checks", key)
		}
	}
}

func TestEveryExampleIsAStepTheToolRuns(t *testing.T) {
	for _, group := range Checks {
		for _, check := range group.Checks {
			decoder := json.NewDecoder(bytes.NewReader([]byte(check.Example)))
			decoder.DisallowUnknownFields()

			var step Step
			if err := decoder.Decode(&step); err != nil {
				t.Errorf("%s: %v", check.Key, err)

				continue
			}

			if err := step.prepare(); err != nil {
				t.Errorf("%s: %v", check.Key, err)
			}

			if written, _ := json.Marshal(step); !bytes.Contains(written, []byte(`"`+check.Key+`"`)) {
				t.Errorf("%s: the example %s makes another check", check.Key, check.Example)
			}
		}
	}
}
