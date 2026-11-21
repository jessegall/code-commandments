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
