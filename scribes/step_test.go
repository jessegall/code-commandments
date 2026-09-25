package scribes

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

// The order Detectors\Catalog::backend() hands the PHP tool's repentable detectors in.
const discoveredByPHP = "HandRolledWitherDetector InlineDocblockDetector LoopInvertedGuardDetector MemberAfterMethodDetector MemberOutOfOrderDetector NestedTernaryDetector RedundantArrowReturnTypeDetector RedundantElseDetector ShortCircuitStatementDetector AllNullableDataDetector ConstructorOrchestrationDetector DataCollectionTypeDetector DataToArrayRoundtripDetector HookMissingComputedDetector ManualHydrationLoopDetector NestedTypeMissingTypeScriptDetector NewDataObjectDetector NonFinalDataDetector PageObjectMissingTypeScriptDetector PreferOptionalCreateDetector RedundantEnumUnwrapDetector RedundantNativeCastDetector RedundantNestedFromDetector StackedDocblockDetector TernaryStatementDetector WrappingWithoutCauseDetector"

func TestRepentableDetectorsRunInThePHPToolsDiscoveryOrder(t *testing.T) {
	var repentable []detectors.Detector
	for _, detector := range detectors.Of(catalog.Backend) {
		if _, ok := detector.(detectors.Repentable); ok {
			repentable = append(repentable, detector)
		}
	}
	slices.SortStableFunc(repentable, func(a, b detectors.Detector) int {
		return strings.Compare(className(catalog.Backend, a), className(catalog.Backend, b))
	})

	var names []string
	for _, detector := range repentable {
		names = append(names, catalog.Name(detector))
	}
	if got := strings.Join(names, " "); got != discoveredByPHP {
		t.Fatalf("got\n%s\nwant\n%s", got, discoveredByPHP)
	}
}

func TestTheDefaultChainRunsEachStageInTurnKeepingTheOrderWithin(t *testing.T) {
	chain := Default(staged{"fix-a", Fixing}, staged{"last", Normalising}, staged{"extract", Extracting}, named("fix-b"), staged{"hints", Maintenance})

	want := []string{"hints", "fix-a", "fix-b", "extract", "last"}
	if got := namesOf(chain); !slices.Equal(got, want) {
		t.Fatalf("got %v, want %v", got, want)
	}
}

type staged struct {
	name  string
	stage Stage
}

func (s staged) Name() string               { return s.name }
func (s staged) Run(Pass) (Rewrites, error) { return Rewrites{}, nil }
func (s staged) Stage() Stage               { return s.stage }
