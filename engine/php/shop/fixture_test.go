package shop_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/php/shop"
	"github.com/jessegall/code-commandments/fixture"
)

func TestEveryBackendDetectorProvesItselfOnTheShop(t *testing.T) {
	fixture.Fixture{Codebase: shop.Codebase(t), Detectors: detectors.Of(catalog.Backend)}.Prove(t)
}

func TestEveryBackendDetectorFlagsWhatItsPhpTwinFlags(t *testing.T) {
	shop.SameFindings(t, detectors.Every(catalog.Backend)...)
}

func TestEveryPhpBackendDetectorIsPorted(t *testing.T) {
	var ported []string
	for _, detector := range detectors.Every(catalog.Backend) {
		ported = append(ported, catalog.Name(detector))
	}
	var missing []string
	for _, php := range shop.PhpRules(t).Detectors {
		if !slices.Contains(ported, php.Name()) {
			missing = append(missing, php.Class)
		}
	}
	if len(missing) > 0 {
		t.Errorf("%d of %d PHP backend detectors have no Go port: %v", len(missing), len(shop.PhpRules(t).Detectors), missing)
	}
}
