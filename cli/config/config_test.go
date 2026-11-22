package config

import (
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
)

var deepNested = Rule{Detector, catalog.Vue, "DeepNestedDetector"}

func TestTheFixtureDeclaresItsLayersThroughConfigure(t *testing.T) {
	config, err := ReadJSON("../../tests/Fixtures/backend/.commandments/config.json")

	if err != nil || len(config.Configurators) != 1 || len(config.Configurators[0].Calls) != 4 {
		t.Fatalf("%+v %v", config, err)
	}

	if call := config.Configurators[0].Calls[3]; call.Method != "layer" || len(call.Args[1].Value.([]any)) != 3 {
		t.Errorf("last layer %+v", call)
	}
}

func TestNoConfigFileIsTheEmptyConfig(t *testing.T) {
	if config, err := Load(t.TempDir()); err != nil || !reflect.DeepEqual(config, Config{}) {
		t.Errorf("%+v %v", config, err)
	}
}

type pointerRule struct{}

func TestARuleRegisteredAsAPointerIsNamedByItsDeclaration(t *testing.T) {
	if byValue, byPointer := ClassOf(Detector, pointerRule{}), ClassOf(Detector, &pointerRule{}); byPointer != byValue {
		t.Errorf("%q is not %q", byPointer, byValue)
	}
}
