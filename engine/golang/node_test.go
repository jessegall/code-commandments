package golang_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
)

var shop = map[string]string{
	"go.mod":         "module example.com/shop\n",
	"money/money.go": "package money\n\ntype Money struct{ cents int }\n\nfunc Of(cents int) Money { return Money{cents: cents} }\n\nfunc (m Money) Cents() int { return m.cents }\n",
	"cart/cart.go":   "package cart\n\nimport (\n\t\"errors\"\n\n\t\"example.com/shop/money\"\n)\n\ntype Cart struct {\n\tlines []money.Money\n}\n\nfunc (c *Cart) Total() money.Money {\n\ttotal := 0\n\tfor _, line := range c.lines {\n\t\ttotal += line.Cents()\n\t}\n\n\treturn money.Of(total)\n}\n\nfunc (c *Cart) settle() error {\n\tif err := c.check(); err != nil {\n\t\treturn err\n\t}\n\n\treturn nil\n}\n\nfunc (c *Cart) check() error {\n\tif len(c.lines) == 0 {\n\t\treturn errors.New(\"empty\")\n\t}\n\n\treturn nil\n}\n\nfunc counted(lines int) bool {\n\treturn lines != 0\n}\n",
	"cart/till.go":   "package cart\n\nfunc ring(c *Cart) int {\n\treturn c.Total().Cents()\n}\n",
}

func TestACapitalIsTheVisibility(t *testing.T) {
	codebase := codebaseOf(t, shop)

	exported := map[string]bool{}
	for _, held := range golang.In(codebase).WhereKind("FuncDecl").Get() {
		exported[held.Name()] = golang.Node{Match: held}.IsExported()
	}

	for name, want := range map[string]bool{"Total": true, "settle": false, "check": false, "Of": true, "Cents": true, "counted": false, "ring": false} {
		if exported[name] != want {
			t.Errorf("%s reads as exported %v", name, exported[name])
		}
	}
}

func TestAMethodBelongsToTheTypeItsReceiverNames(t *testing.T) {
	codebase := codebaseOf(t, shop)

	method := funcNamed(t, codebase, "Total")
	if nested := method.EnclosingType(); nested.Exists() {
		t.Errorf("a Go method sits inside the type declaration %s, which Go does not write", nested.Name())
	}

	owner := golang.Node{Match: method}.OwnerType()
	if !owner.Exists() {
		t.Fatal("a method belongs to no type, where its receiver names one")
	}
	if owner.Name() != "Cart" {
		t.Errorf("the method belongs to %q", owner.Name())
	}
	if owner.Kind() != "TypeSpec" {
		t.Errorf("the type it belongs to is a %s", owner.Kind())
	}

	if held := (golang.Node{Match: funcNamed(t, codebase, "counted")}).OwnerType(); held.Exists() {
		t.Errorf("a function with no receiver belongs to %s", held.Name())
	}
}

func TestTheCallersOfAMethodAreTheEnginesOwnReading(t *testing.T) {
	codebase := codebaseOf(t, shop)

	callers := funcNamed(t, codebase, "Total").Referrers()
	if len(callers) == 0 {
		t.Fatal("a method called from another file of its package has no callers")
	}

	var inRing bool
	for _, caller := range callers {
		if caller.EnclosingFunction().Name() == "ring" {
			inRing = true
		}
	}
	if !inRing {
		t.Error("the call in till.go is not among the method's callers")
	}

	across := funcNamed(t, codebase, "Cents").Referrers()
	if len(across) == 0 {
		t.Error("a method called from another package has no callers")
	}
}

func TestTheErrorGuardIsReadThroughItsType(t *testing.T) {
	codebase := codebaseOf(t, shop)

	var guards, others int
	for _, held := range golang.In(codebase).WhereKind("IfStmt").Get() {
		if (golang.Node{Match: held}).IsErrorCheck() {
			guards++

			continue
		}
		others++
	}

	if guards != 1 {
		t.Errorf("%d conditions read as the error guard, where the module writes one", guards)
	}
	if others == 0 {
		t.Error("no condition reads as something other than the guard, where the module writes one")
	}

	for _, held := range golang.In(codebase).WhereKind("BinaryExpr").Get() {
		node := golang.Node{Match: held}
		if node.Operator() == "!=" && node.Written() == "lines != 0" && node.IsErrorCheck() {
			t.Error("a comparison against zero reads as the error guard")
		}
	}
}

func funcNamed(t *testing.T, codebase *engine.Codebase, name string) engine.Match {
	t.Helper()
	for _, held := range golang.In(codebase).WhereKind("FuncDecl").Get() {
		if held.Name() == name {
			return held
		}
	}
	t.Fatalf("the codebase holds no function %s", name)

	return engine.Match{}
}
