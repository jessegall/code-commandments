package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAGuardTheTypeRulesOutIsFoundAndARealOneIsNot holds the rule to the fault it names: a test for None on a
// parameter annotated as a class the project declares, with no `| None`. An optional annotation, a parameter
// defaulted to None, a type the scan never saw, and a name the def itself sets to None are all guards that
// answer a real question and must pass.
func TestAGuardTheTypeRulesOutIsFoundAndARealOneIsNot(t *testing.T) {
	const order = "class Order:\n    def __init__(self, number: str) -> None:\n        self.number = number\n\n\n"
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a guard the annotation rules out": {order + "def ship(order: Order) -> str:\n    if order is None:\n        return 'none'\n\n    return order.number\n", 1},
		"the same guard the other way":     {order + "def ship(order: Order) -> str:\n    if order is not None:\n        return order.number\n\n    return 'none'\n", 1},
		"an optional annotation":           {order + "def ship(order: Order | None) -> str:\n    if order is None:\n        return 'none'\n\n    return order.number\n", 0},
		"an Optional annotation":           {"from typing import Optional\n\n\n" + order + "def ship(order: Optional[Order]) -> str:\n    if order is None:\n        return 'none'\n\n    return order.number\n", 0},
		"a parameter defaulted to None":    {order + "def ship(order: Order = None) -> str:\n    if order is None:\n        return 'none'\n\n    return order.number\n", 0},
		"a type the scan never saw":        {"def ship(order: 'Shipment') -> str:\n    if order is None:\n        return 'none'\n\n    return order.number\n", 0},
		"a name the def sets to None":      {order + "def ship(order: Order, dropped: bool) -> str:\n    if dropped:\n        order = None\n\n    if order is None:\n        return 'none'\n\n    return order.number\n", 0},
		"no annotation at all":             {order + "def ship(order) -> str:\n    if order is None:\n        return 'none'\n\n    return order.number\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/shipping.py": test.source})
			found := (python.ImpossibleGuardDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
