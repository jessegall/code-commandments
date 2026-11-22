package samples

import (
	"fmt"
	"path/filepath"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestEveryLanguageOwesTheContractButItsCheckedExemptions(t *testing.T) {
	for _, language := range contract.Languages {
		debt, faults := Owed(filepath.Join("..", "coverage"), language)
		if len(faults) > 0 {
			t.Errorf("%s: %v", language, faults)
		}
		if len(debt.Owed)+len(debt.Exemptions) != len(contract.Items()) {
			t.Errorf("%s owes %d and is excused %d of %d items", language, len(debt.Owed), len(debt.Exemptions), len(contract.Items()))
		}
	}
}

func TestAnExemptionMustNameARealItemOnceWithAReason(t *testing.T) {
	table := "# PHP\n\n## Not owed\n\n| Item | Why |\n|---|---|\n" +
		"| `kind/lop` | a typo |\n" +
		"| `kind/loop` | PHP has none |\n" +
		"| `kind/loop` | again |\n" +
		"| `kind/if` |  |\n"
	debt, faults := OwedFrom("php.md", []byte(table))
	var written []string
	for _, fault := range faults {
		written = append(written, fmt.Sprint(fault))
	}
	want := []string{
		"php.md:7: kind/lop is no contract item",
		"php.md:9: kind/loop is exempt twice, first on line 8",
		"php.md:10: kind/if is exempt without a reason",
	}
	if !reflect.DeepEqual(written, want) {
		t.Errorf("faults %q\nwant   %q", written, want)
	}
	if len(debt.Exemptions) != 1 || debt.Exemptions[0].Line != 8 {
		t.Errorf("exemptions %+v, want kind/loop on line 8", debt.Exemptions)
	}
}
