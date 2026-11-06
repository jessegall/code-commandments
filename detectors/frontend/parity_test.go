package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/paritytest"
	"github.com/jessegall/code-commandments/engine/frontend"
)

func served(t testing.TB, _ ...string) []string {
	command, err := frontend.Here().Command()
	if err != nil {
		t.Skip(err)
	}

	return command
}

func TestTheGoDetectorsFindWhatThePHPOnesFind(t *testing.T) {
	paritytest.Compare(t, []catalog.Engine{catalog.Vue, catalog.TypeScript}, served)
}

func TestTheGoDetectorsFindWhatThePHPOnesFoundInTheFixture(t *testing.T) {
	t.Setenv("COMMANDMENTS_PARITY", "../../tests/Fixtures/frontend")
	t.Setenv("COMMANDMENTS_PARITY_FINDINGS", "testdata/fixture.findings")
	t.Setenv("COMMANDMENTS_PARITY_ACCOUNTED", "testdata/fixture.accounted")
	paritytest.Compare(t, []catalog.Engine{catalog.Vue, catalog.TypeScript}, served)
}
