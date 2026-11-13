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
	if asked, _ := askedOfPHP(t, project, shop...); len(asked) != 2 {
		t.Fatalf("the first run asked the bridge for %v, not both files", asked)
	}
	if asked, _ := askedOfPHP(t, project, shop...); len(asked) != 0 {
		t.Errorf("a second run of unchanged files asked the bridge for %v", asked)
	}

	written(t, project, "src/Cart.php", "<?php\n\nnamespace Shop;\n\nfinal class Cart { public function __construct(public ?Order $order) {} }\n")
	asked, stream := askedOfPHP(t, project, shop...)
	if !slices.Equal(asked, []string{filepath.Join(project, "src/Cart.php")}) {
		t.Errorf("an edit to Cart.php asked the bridge for %v, not Cart.php alone", asked)
	}
	if stream.Program != nil && slices.ContainsFunc(stream.Program.Symbols, func(symbol contract.OutsideSymbol) bool { return symbol.Symbol == `Shop\Order` }) {
		t.Errorf("Order, which a file of the scan declares, reads as outside it: %v", stream.Program.Symbols)
	}
}

func askedOfPHP(t *testing.T, checkout string, relative ...string) ([]string, *contract.Stream) {
	t.Helper()

	route := read
	var asked []string
	read = func(b Bridge, roots, files, written []string, tally func()) (*contract.Stream, error) {
		asked = append(asked, written...)

		return route(b, roots, files, written, tally)
	}
	defer func() { read = route }()

	var files []string
	for _, file := range relative {
		files = append(files, filepath.Join(checkout, file))
	}
	stream, err := PHPBridge.ReadShared([]string{filepath.Join(checkout, "src")}, files, func() {}, 1)
	if err != nil {
		t.Fatal(err)
	}
	if len(stream.Files) != len(files) {
		t.Fatalf("the read of %s holds %d files, not %d", checkout, len(stream.Files), len(files))
	}
	slices.Sort(asked)

	return asked, stream
}
