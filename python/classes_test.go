package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/python/pythontest"
)

func TestAClassKnowsItsAncestryAndHowItIsReached(t *testing.T) {
	program := python.In(pythontest.FromSource(t, map[string]string{"orders.py": `
class Record:
    @classmethod
    def from_row(cls, row: dict[str, str]) -> "Record":
        return cls()


class Order(Record):
    pass


class Controller:
    def handle(self, verb: str) -> None:
        getattr(self, verb)()


class Admin(Controller):
    pass


class Plain:
    def handle(self) -> None:
        getattr(self, "run")()
`})).Program
	class := func(name string) python.Node {
		found, ok := program.ClassCalled("orders." + name)
		if !ok {
			t.Fatalf("no class %s", name)
		}

		return found
	}
	if ancestry := program.Ancestry(class("Order")); len(ancestry) != 2 || ancestry[1] != class("Record") {
		t.Errorf("Order's ancestry is %d classes", len(ancestry))
	}
	if !program.IsBuiltFromData(class("Order")) || program.IsBuiltFromData(class("Plain")) {
		t.Error("only Order is built from data")
	}
	if !program.IsDispatchedByName(class("Admin")) || program.IsDispatchedByName(class("Plain")) {
		t.Error("only Controller and its subclasses are dispatched by name")
	}
	if program.DeclaresClass("elsewhere.Missing") {
		t.Error("an undeclared class is declared")
	}
}
