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
// first line names. Compare against a snapshot at that commit, never a working copy someone is editing, holding what
// the bridge reads beside the committed sources — each project's obj/project.assets.json (its packages) and
// obj/**/*.GlobalUsings.g.cs (its global usings), and the gitignored sources a build generates — copied from a
// checkout whose project and package files have not changed since:
//
//	git -C chronos archive <commit> | tar -x -C snapshot
//	rsync -a --prune-empty-dirs --exclude=.git/ --exclude=node_modules/ --include='*/' \
//	    --include=project.assets.json --include='*.GlobalUsings.g.cs' --exclude='*' chronos/ snapshot/
//	cp -p chronos/src/Host/Internal/Generated/WolverineHandlers/*.cs snapshot/src/Host/Internal/Generated/WolverineHandlers/
//	GOMEMLIMIT=3GiB GOMAXPROCS=2 COMMANDMENTS_PARITY=snapshot COMMANDMENTS_PARITY_EACH=1 \
//	    COMMANDMENTS_PARITY_FINDINGS=$PWD/engine/csharp/testdata/chronos.findings.each.gz \
//	    go test -p 1 -run TestTheGoDetectorsFindWhatThePHPOnesFind ./detectors/csharp/
func TestTheGoDetectorsFindWhatThePHPOnesFind(t *testing.T) {
	paritytest.Compare(t, catalog.CSharp, "../../engine/csharp/testdata/findings.php", bridge.TestRoslyn)
}
