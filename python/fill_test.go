package python_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/python/pythontest"
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

// callTo is the call in the file whose callee is spelled as given.
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

func TestACallIsTargetedAtTheDefItReaches(t *testing.T) {
	codebase := pythontest.FromSource(t, shop)
	checkout := pythontest.File(t, codebase, "shop/checkout.py")
	reaches := map[string]string{
		"helper":        "shop.checkout.Till.ring.helper",
		"local":         "shop.checkout.local",
		"summed":        "shop.pricing.total",
		"prices.total":  "shop.pricing.total",
		"pricing.total": "shop.pricing.total",
		"self.cart.add": "shop.pricing.Cart.add",
		"other.size":    "shop.pricing.Cart.size",
		"self.ring":     "shop.checkout.Till.ring",
	}
	for spelled, symbol := range reaches {
		target := callTo(t, checkout, spelled).Target
		if target == nil || target.Symbol != symbol {
			t.Errorf("%s targets %+v, not %s", spelled, target, symbol)
		}
	}
	if target := callTo(t, checkout, "self.cart.add").Target; target.Type != "shop.pricing.Cart" || target.Name != "add" {
		t.Errorf("a method's target names %+v", target)
	}
	for _, unresolved := range []string{"unknown", "Decimal"} {
		if target := callTo(t, checkout, unresolved).Target; target != nil {
			t.Errorf("%s targets %+v", unresolved, target)
		}
	}
}

func TestAMethodABaseDeclaresIsReachedThroughTheBase(t *testing.T) {
	pricing := pythontest.File(t, pythontest.FromSource(t, shop), "shop/pricing.py")
	if target := callTo(t, pricing, "self.size").Target; target == nil || target.Symbol != "shop.pricing.Cart.size" {
		t.Errorf("self.size targets %+v", target)
	}
}

func TestAnOverridingMethodIsInherited(t *testing.T) {
	pricing := pythontest.File(t, pythontest.FromSource(t, shop), "shop/pricing.py")
	for _, node := range pricing.Nodes() {
		if node.Kind != "FunctionDef" {
			continue
		}
		overrides := node.Symbol == "shop.pricing.BigCart.add"
		if node.Inherited != overrides {
			t.Errorf("%s is inherited: %v", node.Symbol, node.Inherited)
		}
	}
}

func TestANameRefersToWhatItsModuleBindsUnlessAFunctionBindsItItself(t *testing.T) {
	checkout := pythontest.File(t, pythontest.FromSource(t, shop), "shop/checkout.py")
	var refers []string
	for _, node := range checkout.Nodes() {
		if node.Kind == "Name" && node.Name == "Decimal" {
			refers = append(refers, node.Refers)
		}
	}
	if strings.Join(refers, ",") != "decimal.Decimal,decimal.Decimal," {
		t.Errorf("the Decimal names refer to %q", refers)
	}
}

func TestAnImportResolvesToTheFileItReaches(t *testing.T) {
	checkout := pythontest.File(t, pythontest.FromSource(t, shop), "shop/checkout.py")
	resolved := map[string]string{}
	for _, node := range checkout.Nodes() {
		if node.Kind == "alias" {
			resolved[node.Name] = node.Resolves
		}
	}
	if !strings.HasSuffix(resolved["shop.pricing"], "/shop/pricing.py") || !strings.HasSuffix(resolved["Cart"], "/shop/pricing.py") {
		t.Errorf("the imports resolve to %v", resolved)
	}
	if resolved["Decimal"] != "" {
		t.Errorf("an import from outside the codebase resolves to %s", resolved["Decimal"])
	}
}

func TestAWrittenTypeNamesTheClassItSpells(t *testing.T) {
	checkout := pythontest.File(t, pythontest.FromSource(t, shop), "shop/checkout.py")
	names := map[string]string{}
	for _, node := range checkout.Nodes() {
		if node.Kind == "arg" && node.Declared != nil {
			names[node.Name] = node.Declared.Name
		}
		if node.Kind == "FunctionDef" && node.Name == "ring" {
			names["ring"] = node.Returns.Name
		}
	}
	if names["other"] != "shop.pricing.Cart" || names["ring"] != "decimal.Decimal" || names["Decimal"] != "int" {
		t.Errorf("the written types name %v", names)
	}
}

func TestArithmeticOnLiteralsIsConstant(t *testing.T) {
	checkout := pythontest.File(t, pythontest.FromSource(t, shop), "shop/checkout.py")
	for _, node := range checkout.Nodes() {
		if node.Kind == "BinOp" && !node.Constant {
			t.Error("1 + 2 is not constant")
		}
	}
}
