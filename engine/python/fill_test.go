package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

var shop = map[string]string{
	"shop/__init__.py": "",
	"shop/pricing.py": `
def total(amount: int) -> int:
    return amount


class Cart:
    def add(self, item: str) -> None:
        self.items = [item]

    def size(self) -> int:
        return 1


class BigCart(Cart):
    def add(self, item: str) -> None:
        self.size()
`,
	"shop/checkout.py": `
from decimal import Decimal
import shop.pricing as prices
from . import pricing
from .pricing import Cart, total as summed


def local() -> None:
    pass


class Till:
    def __init__(self, cart: Cart) -> None:
        self.cart = cart

    def ring(self, other: Cart) -> Decimal:
        def helper() -> None:
            pass

        helper()
        local()
        summed(1)
        prices.total(2)
        pricing.total(3)
        self.cart.add("x")
        other.size()
        self.ring(other)
        unknown()
        limit = 1 + 2
        return Decimal(limit)


def shadowed(Decimal: int) -> int:
    return Decimal
`,
}

func callTo(t *testing.T, file *engine.File, spelled string) *contract.Node {
	t.Helper()
	source, _ := file.Source()
	for _, node := range file.Nodes() {
		callee := file.Match(node.ID).Child("func")
		if node.Kind == "Call" && callee.Exists() && string(source[callee.Node().Span.Start:callee.Node().Span.End]) == spelled {
			return node
		}
	}
	t.Fatalf("no call to %s", spelled)

	return nil
}

func TestAnOverridingMethodIsInherited(t *testing.T) {
	pricing := pythontest.File(t, pythontest.FromSource(t, shop), "shop/pricing.py")
	for _, node := range pricing.Nodes() {
		if node.Kind != "FunctionDef" {
			continue
		}
		overrides := node.Symbol == "shop.pricing.BigCart.add"
		if node.Incomplete != overrides {
			t.Errorf("%s is inherited: %v", node.Symbol, node.Incomplete)
		}
	}
}

func TestArithmeticOnLiteralsIsConstant(t *testing.T) {
	checkout := pythontest.File(t, pythontest.FromSource(t, shop), "shop/checkout.py")
	for _, node := range checkout.Nodes() {
		if node.Kind == "BinOp" && !node.Incomplete {
			t.Error("1 + 2 is not constant")
		}
	}
}
