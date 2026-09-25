package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/paritytest"
)

// TestTheGoDetectorsFindWhatThePHPOnesFind runs both engines' C# detectors over the solution $COMMANDMENTS_PARITY
// names and fails for every finding only one of them makes.
func TestTheGoDetectorsFindWhatThePHPOnesFind(t *testing.T) {
	paritytest.Compare(t, catalog.CSharp, "../../engine/csharp/testdata/findings.php", bridge.TestRoslyn)
}
