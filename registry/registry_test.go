package registry_test

import (
	"bytes"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
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
