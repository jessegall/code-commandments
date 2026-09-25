package shop_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine/php/shop"
	"github.com/jessegall/code-commandments/fixture"
)

// tunings are the shop's own settings, as tests/Fixtures/backend/.commandments/config.php declares them for judge.
var tunings = []detectors.Tuning{
	detectors.Tune(func(d backend.NamespaceDependencyDetector) backend.NamespaceDependencyDetector {
		return d.
			Layer(`Shop\Ui\Tokens`).
			Layer(`Shop\Ui\Elements`, `Shop\Ui\Tokens`).
			Layer(`Shop\Ui\Shared`, `Shop\Ui\Elements`, `Shop\Ui\Tokens`).
			Layer(`Shop\Ui\Pages`, `Shop\Ui\Shared`, `Shop\Ui\Elements`, `Shop\Ui\Tokens`)
	}),
}

// tuned is the detectors set as the shop's config sets them.
func tuned(t *testing.T, registered []detectors.Detector) []detectors.Detector {
	t.Helper()
	set, unmatched := detectors.Tuned(registered, tunings...)
	if len(unmatched) > 0 {
		t.Fatalf("the shop tunes detectors that are not registered: %v", unmatched)
	}

	return set
}

func TestEveryBackendDetectorProvesItselfOnTheShop(t *testing.T) {
	fixture.Fixture{Codebase: shop.Codebase(t), Detectors: tuned(t, detectors.Of(catalog.Backend))}.Prove(t)
}

func TestEveryBackendDetectorFlagsWhatItsPhpTwinFlags(t *testing.T) {
	shop.SameFindings(t, tuned(t, detectors.Every(catalog.Backend))...)
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
