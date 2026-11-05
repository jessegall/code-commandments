package warm_test

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/engine"
)

// TestAViewFilledForWhatChangedAnswersAsOneFilledWhole holds the one thing the saving must not cost. A view
// writes the engine's own facts again only for the files it replaces, since every other file is the base's own
// node carrying the facts it was read with; and a fact crosses modules, so what reaches a changed file is filled
// with it. This asks the same question of a view filled that way and of one filled whole, and requires the same
// answer.
func TestAViewFilledForWhatChangedAnswersAsOneFilledWhole(t *testing.T) {
	root, codebase := project(t, map[string]string{
		"shop/money.py": `class Money:
    def cents(self) -> int:
        return 0
`,
		"shop/order.py": `from shop.money import Money


class Order:
    def total(self) -> Money:
        return Money()
`,
	})

	base := warm.Keep().Hold(root, "main", []string{root}, codebase)

	// The class the other module names loses the method that module reads.
	money := filepath.Join(root, "shop", "money.py")
	write(t, money, `class Money:
    def amount(self) -> int:
        return 0
`)

	rebuilt, err := scan.OneFile(money).Load()
	if err != nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	partly := base.View(rebuilt.Streams(), root)
	whole := engine.Load(partly.Streams()...)
	whole.Scanned(root)
	whole.Rooted(root)

	for _, asked := range []struct {
		name  string
		count func(*engine.Codebase) int
	}{
		{"the methods every type declares", func(c *engine.Codebase) int { return c.WhereFunction().Count() }},
		{"what the calls refer to", func(c *engine.Codebase) int {
			refers := 0
			for _, match := range c.WhereCall().Get() {
				if match.Identity() != "" {
					refers++
				}
			}

			return refers
		}},
		{"the types the declarations name", func(c *engine.Codebase) int {
			named := 0
			for _, match := range c.WhereTypeDeclaration().Get() {
				named += len(match.Children())
			}

			return named
		}},
	} {
		if filled, all := asked.count(partly), asked.count(whole); filled != all {
			t.Errorf("%s: the view filled for what changed answers %d, filled whole %d", asked.name, filled, all)
		}
	}
}
