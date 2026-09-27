package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/paritytest"
)

// TestTheGoDetectorsFindWhatThePHPOnesFind runs both engines' Python detectors over the project $COMMANDMENTS_PARITY
// names and fails for every finding only one of them makes.
func TestTheGoDetectorsFindWhatThePHPOnesFind(t *testing.T) {
	paritytest.Compare(t, []catalog.Engine{catalog.Python}, "../../engine/python/testdata/findings.php", func(t testing.TB, _ ...string) []string { return bridge.TestMypy(t) })
}
