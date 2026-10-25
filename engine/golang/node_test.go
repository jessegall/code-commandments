package golang_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
)

// shop is a module whose shapes the decorator is asked about: an exported method and an unexported one, a method
// on a pointer receiver, a call across packages, and Go's own error guard beside a comparison that only looks
// like one.
var shop = map[string]string{
	"go.mod":         "module example.com/shop\n",
	"money/money.go": "package money\n\ntype Money struct{ cents int }\n\nfunc Of(cents int) Money { return Money{cents: cents} }\n\nfunc (m Money) Cents() int { return m.cents }\n",
	"cart/cart.go":   "package cart\n\nimport (\n\t\"errors\"\n\n\t\"example.com/shop/money\"\n)\n\ntype Cart struct {\n\tlines []money.Money\n}\n\nfunc (c *Cart) Total() money.Money {\n\ttotal := 0\n\tfor _, line := range c.lines {\n\t\ttotal += line.Cents()\n\t}\n\n\treturn money.Of(total)\n}\n\nfunc (c *Cart) settle() error {\n\tif err := c.check(); err != nil {\n\t\treturn err\n\t}\n\n\treturn nil\n}\n\nfunc (c *Cart) check() error {\n\tif len(c.lines) == 0 {\n\t\treturn errors.New(\"empty\")\n\t}\n\n\treturn nil\n}\n\nfunc counted(lines int) bool {\n\treturn lines != 0\n}\n",
	"cart/till.go":   "package cart\n\nfunc ring(c *Cart) int {\n\treturn c.Total().Cents()\n}\n",
}

// TestACapitalIsTheVisibility holds the decorator to Go's own rule: a name's first letter says whether the rest
// of the program may read it, and there is no keyword to read instead.
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

// TestAMethodBelongsToTheTypeItsReceiverNames holds the one fact Go needs that nesting cannot give: a method is
// declared outside its type, so what it belongs to is the type its receiver names, joined by the id each carries.
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

// TestTheCallersOfAMethodAreTheEnginesOwnReading holds the Go call graph to the one the engine already has: the
// bridge writes what each call reaches and what each name refers to, so the engine's own index answers the
// callers of a declaration, across files and across packages. There is no Go call graph of its own to write, and
// the rules that need one need nothing new.
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

// TestTheErrorGuardIsReadThroughItsType holds Go's own guard to the type the checker gave, never to the name
// `err`: a project may call it anything, and a value named err that is no error is not this shape. Every rule
// about a repeated condition or a nested one pardons it, so reading it wrongly would silence or raise dozens.
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

// funcNamed is the codebase's function declaration of that name.
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
