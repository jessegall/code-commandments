package scan

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cache"
)

// C# reads through the one cache like every language: a second run of unchanged files asks the bridge for nothing, an
// edit inside a body asks for that file alone, and a changed declaration asks for the files that read it too.
func TestCSharpReadsThroughTheOneCache(t *testing.T) {
	if _, err := bridge.Roslyn(); err != nil {
		t.Skipf("no C# bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/Order.cs", "namespace Shop;\n\npublic sealed class Order\n{\n    public int Total()\n    {\n        return 3;\n    }\n}\n")
	written(t, project, "src/Cart.cs", "namespace Shop;\n\npublic sealed class Cart\n{\n    public int Sum(Order order)\n    {\n        return order.Total();\n    }\n}\n")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	if asked := askedOfCSharpShop(t, project); len(asked) != 2 {
		t.Fatalf("the first run asked the bridge for %v, not both files", asked)
	}
	if asked := askedOfCSharpShop(t, project); len(asked) != 0 {
		t.Errorf("a second run of unchanged files asked the bridge for %v", asked)
	}

	written(t, project, "src/Order.cs", "namespace Shop;\n\npublic sealed class Order\n{\n    public int Total()\n    {\n        return 4;\n    }\n}\n")
	if asked := askedOfCSharpShop(t, project); !slices.Equal(asked, []string{filepath.Join(project, "src/Order.cs")}) {
		t.Errorf("an edit inside Total's body asked the bridge for %v, not Order.cs alone: Cart.cs reads only what Order declares", asked)
	}

	written(t, project, "src/Order.cs", "namespace Shop;\n\npublic sealed class Order\n{\n    public long Total()\n    {\n        return 4;\n    }\n}\n")
	if asked := askedOfCSharpShop(t, project); !slices.Equal(asked, []string{filepath.Join(project, "src/Cart.cs"), filepath.Join(project, "src/Order.cs")}) {
		t.Errorf("a change to what Order declares asked the bridge for %v, not Order.cs and Cart.cs, which reads it", asked)
	}
}

func askedOfCSharpShop(t *testing.T, project string) []string {
	t.Helper()
	asked, _ := askedOf(t, CSharp, project, "src/Cart.cs", "src/Order.cs")

	return asked
}
