package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAFinderEveryCallerDeNullsIsFoundAndOneWithARealChoiceIsNot holds the measure the rule is built on: the
// annotation says the value may be missing, and the question is whether anybody agrees. Two callers turning the
// None into a failure make it the finder's business; a caller that has something else to do about the absence is
// the signature telling the truth, and is left alone.
func TestAFinderEveryCallerDeNullsIsFoundAndOneWithARealChoiceIsNot(t *testing.T) {
	const orders = "class Order:\n    def __init__(self, reference: str) -> None:\n        self.reference = reference\n\n\nclass Orders:\n    def __init__(self) -> None:\n        self.kept: dict[str, Order] = {}\n\n    def find(self, reference: str) -> Order | None:\n        return self.kept.get(reference)\n"

	for name, test := range map[string]struct {
		callers string
		want    int
	}{
		"two callers reading straight through it": {
			"from shop.orders import Orders\n\n\ndef label(orders: Orders, reference: str) -> str:\n    return orders.find(reference).reference\n\n\ndef shout(orders: Orders, reference: str) -> str:\n    return orders.find(reference).reference.upper()\n",
			1,
		},
		"two callers raising on the absence": {
			"from shop.orders import Orders\n\n\nclass Missing(Exception):\n    pass\n\n\ndef label(orders: Orders, reference: str) -> str:\n    order = orders.find(reference)\n    if order is None:\n        raise Missing(reference)\n    return order.reference\n\n\ndef shout(orders: Orders, reference: str) -> str:\n    found = orders.find(reference)\n    if found is None:\n        raise Missing(reference)\n    return found.reference.upper()\n",
			1,
		},
		"two callers asserting it away": {
			"from shop.orders import Orders\n\n\ndef label(orders: Orders, reference: str) -> str:\n    order = orders.find(reference)\n    assert order is not None\n    return order.reference\n\n\ndef shout(orders: Orders, reference: str) -> str:\n    found = orders.find(reference)\n    assert found is not None\n    return found.reference.upper()\n",
			1,
		},
		"a caller with something else to do": {
			"from shop.orders import Orders\n\n\ndef label(orders: Orders, reference: str) -> str:\n    return orders.find(reference).reference\n\n\ndef labelled(orders: Orders, reference: str) -> str:\n    order = orders.find(reference)\n    if order is None:\n        return 'unknown'\n    return order.reference\n",
			0,
		},
		"one caller only": {
			"from shop.orders import Orders\n\n\ndef label(orders: Orders, reference: str) -> str:\n    return orders.find(reference).reference\n",
			0,
		},
		"no caller at all": {
			"from shop.orders import Orders\n\n\ndef count(orders: Orders) -> int:\n    return len(orders.kept)\n",
			0,
		},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/orders.py": orders, "shop/labels.py": test.callers})
			found := (python.DeNulledFinderDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
