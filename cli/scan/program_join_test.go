package scan

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

// A program line read again replaces what an earlier run read of each symbol, and its loader warning stands as the
// fresh run says it: after composer update one entry of a symbol is left, and after composer install no warning.
func TestAFreshProgramReplacesEachSymbolAndTheLoaderWarning(t *testing.T) {
	kept := contract.Program{
		Symbols: []contract.OutsideSymbol{{Symbol: `Acme\Sheet`, Kind: "class"}, {Symbol: `Acme\Cell`, Kind: "class"}},
		Loader:  "run composer install",
	}
	joined := joinedPrograms(kept, &contract.Program{Symbols: []contract.OutsideSymbol{{Symbol: `Acme\Sheet`, Kind: "interface"}}})

	if len(joined.Symbols) != 2 || joined.Symbols[0].Kind != "interface" {
		t.Errorf("Acme\\Sheet, read again as an interface, is joined as %v", joined.Symbols)
	}
	if joined.Loader != "" {
		t.Errorf("a fresh run whose loader loaded still warns %q", joined.Loader)
	}
}

// A symbol a file of the scan declares is never named outside it, however the bridge named it.
func TestAProgramNamesNothingAFileOfTheScanDeclares(t *testing.T) {
	order := &contract.Node{ID: 1, Field: "statements", Kind: "class", Name: "Order", Facts: &contract.Facts{Symbol: `Shop\Order`}}
	file := &contract.File{Path: "/shop/Order.php", Root: &contract.Node{Kind: "file", Children: []*contract.Node{order}}}
	if err := file.Linked(); err != nil {
		t.Fatal(err)
	}
	program := outsideOnly(&contract.Program{Symbols: []contract.OutsideSymbol{{Symbol: `Shop\Order`}, {Symbol: `Acme\Sheet`}}}, []*contract.File{file})

	if len(program.Symbols) != 1 || program.Symbols[0].Symbol != `Acme\Sheet` {
		t.Errorf("the program names %v outside the scan", program.Symbols)
	}
}
