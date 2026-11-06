package registry_test

import (
	"bytes"
	"fmt"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/scribes"
	_ "github.com/jessegall/code-commandments/scribes/repent"
)

func TestTheRegistryEnrolsEveryRuleFolder(t *testing.T) {
	want, err := catalog.Enrolment("..")
	if err != nil {
		t.Fatal(err)
	}
	got, err := os.ReadFile("registry.go")
	if err != nil {
		t.Fatal(err)
	}

	if !bytes.Equal(got, want) {
		t.Fatalf("registry/registry.go is stale: run go generate ./registry\n%s", want)
	}
}

func TestANameSeveralEnginesShareMeansNoOneRule(t *testing.T) {
	shared := detectors.EveryNamedIn(detectors.All(), "DeepNestingDetector")
	if len(shared) < 2 {
		t.Fatalf("want DeepNestingDetector in several engines, got %d", len(shared))
	}

	if rule, found := detectors.Named("DeepNestingDetector"); found {
		engine, _ := detectors.EngineOf(rule)
		t.Fatalf("want no rule for a name %d engines share, got the %s one", len(shared), engine)
	}
}

func TestANameGivenItsEngineMeansThatEnginesRule(t *testing.T) {
	for _, engine := range []catalog.Engine{catalog.PHP, catalog.Python, catalog.CSharp, catalog.Go} {
		rule, found := detectors.Named(string(engine) + "/DeepNestingDetector")
		if !found {
			t.Fatalf("%s/DeepNestingDetector means no rule", engine)
		}

		if own, _ := detectors.EngineOf(rule); own != engine {
			t.Fatalf("%s/DeepNestingDetector means the %s rule", engine, own)
		}
	}
}

func TestAScribeIsNeverHandedToAnotherEnginesRuleOfTheSameName(t *testing.T) {
	fixed := 0
	for _, rule := range detectors.All() {
		scribe, fixes := scribes.ScribeFor(rule)
		if !fixes {
			continue
		}
		fixed++

		engine, _ := detectors.EngineOf(rule)
		for _, twin := range detectors.EveryNamedIn(detectors.All(), catalog.Name(rule)) {
			own, _ := detectors.EngineOf(twin)
			if own == engine {
				continue
			}

			if theirs, also := scribes.ScribeFor(twin); also && fmt.Sprintf("%T", theirs) == fmt.Sprintf("%T", scribe) {
				t.Fatalf("the %s %s scribe is handed to the %s rule of that name", engine, catalog.Name(rule), own)
			}
		}
	}

	if fixed == 0 {
		t.Fatal("no rule has a scribe at all, so nothing was asked")
	}
}
