package shop_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine/php/shop"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
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
	fixturetest.Prove(t, fixture.Fixture{Codebase: shop.Project(t), Detectors: tuned(t, detectors.Of(catalog.Backend))})
}

func TestEveryBackendDetectorFlagsWhatItsPhpTwinFlags(t *testing.T) {
	shop.SameFindings(t, tuned(t, detectors.Every(catalog.Backend))...)
}
