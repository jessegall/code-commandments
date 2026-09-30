package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/paritytest"
	"github.com/jessegall/code-commandments/engine/frontend"
)

// served is the frontend bridge's command, which the check serves every part through.
func served(t testing.TB, _ ...string) []string {
	command, err := frontend.Here().Command()
	if err != nil {
		t.Skip(err)
	}

	return command
}

// TestTheGoDetectorsFindWhatThePHPOnesFind runs both tools' Vue and TypeScript detectors over the project
// $COMMANDMENTS_PARITY names and fails for every finding only one of them makes.
func TestTheGoDetectorsFindWhatThePHPOnesFind(t *testing.T) {
	paritytest.Compare(t, []catalog.Engine{catalog.Frontend, catalog.TypeScript}, served)
}

// TestTheGoDetectorsFindWhatThePHPOnesFoundInTheFixture compares both tools over the frontend fixture, against the
// PHP findings kept in testdata/fixture.findings: a committed half that needs no PHP to read back. What only one
// tool finds is written down with its cause in testdata/fixture.accounted.
func TestTheGoDetectorsFindWhatThePHPOnesFoundInTheFixture(t *testing.T) {
	t.Setenv("COMMANDMENTS_PARITY", "../../tests/Fixtures/frontend")
	t.Setenv("COMMANDMENTS_PARITY_FINDINGS", "testdata/fixture.findings")
	t.Setenv("COMMANDMENTS_PARITY_ACCOUNTED", "testdata/fixture.accounted")
	paritytest.Compare(t, []catalog.Engine{catalog.Frontend, catalog.TypeScript}, served)
}
