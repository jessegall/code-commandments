package shop_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/detectors/backend/laravel"
	"github.com/jessegall/code-commandments/engine/php/shop"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
)

func TestEveryBackendDetectorProvesItselfOnTheShop(t *testing.T) {
	fixturetest.Prove(t, fixture.Fixture{
		Codebase:  shop.Project(t),
		Detectors: fixture.Proven(fixturetest.Configured(t, shop.Fixture(), detectors.Of(catalog.PHP))),
		Known:     fixture.Proven(detectors.Every(catalog.PHP)),
	})
}

func TestUndeclaredMemberRecognizesTheDeclaredContract(t *testing.T) {
	fixturetest.Prove(t, fixture.Fixture{
		Codebase: shop.Project(t).Reporting(
			shop.Root+"/app/Bins/BinLabelCopies.php",
			shop.Root+"/app/Exports/ExportRun.php",
			shop.Root+"/app/Vouchers/VoucherReceipt.php",
		),
		Detectors: fixture.Proven([]detectors.Detector{shipped(t, "undeclared-member")}),
		Known:     fixture.Proven(detectors.Every(catalog.PHP)),
	})
}

func TestAssembledTemplateProvesItselfOnTheShop(t *testing.T) {
}

func TestBareScopeEscapeProvesItselfOnTheShop(t *testing.T) {
	proveBackendDetector(t, shipped(t, "bare-scope-escape"))
}

func TestBlankStringOnTheWireProvesItselfOnTheShop(t *testing.T) {
	proveBackendDetector(t, backend.BlankStringOnTheWireDetector{})
}

func TestBloatedDocblockProvesItselfOnTheShop(t *testing.T) {
	proveBackendDetector(t, shipped(t, "bloated-docblock"))
}

func TestBoundaryDuplicatedOperationProvesItselfOnTheShop(t *testing.T) {
	proveBackendDetector(t, laravel.BoundaryDuplicatedOperationDetector{})
}

func TestCeremonyDocblockProvesItselfOnTheShop(t *testing.T) {
	proveBackendDetector(t, shipped(t, "ceremony-docblock"))
}

func proveBackendDetector(t *testing.T, detector detectors.Detector) {
	t.Helper()
	fixturetest.Prove(t, fixture.Fixture{
		Codebase:  shop.Project(t),
		Detectors: fixture.Proven([]detectors.Detector{detector}),
		Known:     fixture.Proven(detectors.Every(catalog.PHP)),
	})
}

func TestEveryBackendDetectorFlagsWhatItsPhpTwinFlags(t *testing.T) {
	shop.SameFindings(t, fixturetest.Configured(t, shop.Fixture(), detectors.Every(catalog.PHP))...)
}
