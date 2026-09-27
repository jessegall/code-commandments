package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors/paritytest"
)

// TestTheGoDetectorsFindWhatThePHPOnesFind runs both engines' C# detectors over the solution $COMMANDMENTS_PARITY
// names and fails for every finding only one of them makes.
//
// The kept answer, testdata/chronos.findings.each.gz, is the PHP engine's per project on Chronos at the commit its
// first line names. Compare against the snapshot scripts/memory/snapshot.sh makes at that commit, never a working
// copy someone is editing: the committed sources, the restore output made in a capped container, and the sources a
// build generates.
//
//	scripts/memory/snapshot.sh chronos ac38efd04 snapshot
//	scripts/dev --mount snapshot --mount ~/.nuget/packages sh -c 'NUGET_PACKAGES=~/.nuget/packages \
//	    COMMANDMENTS_PARITY=snapshot COMMANDMENTS_PARITY_EACH=1 \
//	    COMMANDMENTS_PARITY_FINDINGS=$PWD/engine/csharp/testdata/chronos.findings.each.gz \
//	    go test -p 1 -timeout 120m -run TestTheGoDetectorsFindWhatThePHPOnesFind ./detectors/csharp/'
func TestTheGoDetectorsFindWhatThePHPOnesFind(t *testing.T) {
	paritytest.Compare(t, []catalog.Engine{catalog.CSharp}, "../../engine/csharp/testdata/findings.php", bridge.TestRoslyn)
}
