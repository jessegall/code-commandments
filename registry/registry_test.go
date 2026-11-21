package registry_test

import (
	"bytes"
	"os"
	"slices"
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

func TestANameOneRuleOfSeveralEnginesAnswersToIsThatRule(t *testing.T) {
	named := detectors.EveryNamedIn(detectors.All(), "DeepNestingDetector")
	if len(named) != 1 || len(detectors.EnginesOf(named[0])) < 2 {
		t.Fatalf("want the one deep-nesting rule of several engines, got %d rules", len(named))
	}
	if rule, found := detectors.Named("DeepNestingDetector"); !found || rule.Sin().Definition().Name != "deep-nesting" {
		t.Fatalf("DeepNestingDetector names no single rule")
	}
}

func TestANameGivenItsEngineMeansTheRuleJudgingThatEngine(t *testing.T) {
	for _, engine := range []catalog.Engine{catalog.PHP, catalog.Python, catalog.CSharp, catalog.Go} {
		rule, found := detectors.Named(string(engine) + "/DeepNestingDetector")
		if !found {
			t.Fatalf("%s/DeepNestingDetector means no rule", engine)
		}
		if !slices.Contains(detectors.EnginesOf(rule), engine) {
			t.Fatalf("%s/DeepNestingDetector means a rule that does not judge %s", engine, engine)
		}
	}
}
