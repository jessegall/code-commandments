package scan

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/php"
)

// PHP reads through the one cache like every language: a second run of unchanged files asks the bridge for nothing,
// an edit asks for that file alone, and a class a file served from the cache declares never reads as outside the scan.
func TestPHPReadsThroughTheOneCache(t *testing.T) {
	if _, err := php.Here().Command(); err != nil {
		t.Skipf("no PHP bridge here: %v", err)
	}
	project := repository(t)
	journalDriven(t, project)
	written(t, project, "src/Order.php", "<?php\n\nnamespace Shop;\n\nfinal class Order {}\n")
	written(t, project, "src/Cart.php", "<?php\n\nnamespace Shop;\n\nfinal class Cart { public function __construct(public Order $order) {} }\n")

	held := cache.KeptIn()
	cache.KeepIn(t.TempDir())
	t.Cleanup(func() { cache.KeepIn(held) })

	shop := []string{"src/Cart.php", "src/Order.php"}
	if asked, _ := askedOf(t, PHPBridge, project, shop...); len(asked) != 2 {
		t.Fatalf("the first run asked the bridge for %v, not both files", asked)
	}
	if asked, _ := askedOf(t, PHPBridge, project, shop...); len(asked) != 0 {
		t.Errorf("a second run of unchanged files asked the bridge for %v", asked)
	}

	written(t, project, "src/Cart.php", "<?php\n\nnamespace Shop;\n\nfinal class Cart { public function __construct(public ?Order $order) {} }\n")
	asked, stream := askedOf(t, PHPBridge, project, shop...)
	if !slices.Equal(asked, []string{filepath.Join(project, "src/Cart.php")}) {
		t.Errorf("an edit to Cart.php asked the bridge for %v, not Cart.php alone", asked)
	}
	if stream.Program != nil && slices.ContainsFunc(stream.Program.Symbols, func(symbol contract.OutsideSymbol) bool { return symbol.Symbol == `Shop\Order` }) {
		t.Errorf("Order, which a file of the scan declares, reads as outside it: %v", stream.Program.Symbols)
	}
}
