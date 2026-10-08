package python_test

import (
	"testing"

	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAnEntityThatComparesByIdentityIsNoMutableValue holds the rule to values: a dataclass that gives up value
// equality with eq=False is an entity known by its identity, and changing through its life is what it is for.
func TestAnEntityThatComparesByIdentityIsNoMutableValue(t *testing.T) {
	entity := "from dataclasses import dataclass\n\n\n@dataclass(eq=False)\nclass Shipment:\n    number: int\n    status: str = \"packed\"\n\n    def dispatch(self) -> None:\n        self.status = \"dispatched\"\n"
	value := "from dataclasses import dataclass\n\n\n@dataclass\nclass Shipment:\n    number: int\n    status: str = \"packed\"\n\n    def dispatch(self) -> None:\n        self.status = \"dispatched\"\n"

	if found := (pydetectors.MutableValueObjectDetector{}).Find(pythontest.FromSource(t, map[string]string{"shipment.py": entity})); len(found) != 0 {
		t.Errorf("an entity was flagged at %v", found[0].Location())
	}
	if found := (pydetectors.MutableValueObjectDetector{}).Find(pythontest.FromSource(t, map[string]string{"shipment.py": value})); len(found) != 1 {
		t.Errorf("a value written after construction was flagged %d times", len(found))
	}
}
