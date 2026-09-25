package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/python/pythontest"
)

func TestTheSelectorsOpenQueriesOverPythonNodes(t *testing.T) {
	codebase := python.In(pythontest.FromSource(t, map[string]string{"till.py": `
def ring(amount: list[int] = [1]) -> None:
    square = lambda n: n * n
    print(square(amount))


class Till:
    def open(self) -> None:
        pass
`}))
	counts := map[string]int{
		"functions":           codebase.WhereFunction().Count(),
		"method declarations": codebase.WhereMethodDeclaration().Count(),
		"classes":             codebase.WhereClass().Count(),
		"calls":               codebase.WhereCall().Count(),
		"statements":          codebase.WhereStatement().Count(),
	}
	want := map[string]int{"functions": 2, "method declarations": 1, "classes": 1, "calls": 2, "statements": 6}
	for selector, count := range want {
		if counts[selector] != count {
			t.Errorf("%d %s, not %d", counts[selector], selector, count)
		}
	}
	for _, expression := range codebase.WhereExpression().Get() {
		if expression.Name() == "int" || expression.Name() == "list" {
			t.Errorf("the annotation's %s is read as an expression", expression.Name())
		}
	}
}
