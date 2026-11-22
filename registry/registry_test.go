package registry_test

import (
	"bytes"
	"os"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/rule"
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
	named := rule.EveryNamedIn(rule.All(), "DeepNestingDetector")
	if len(named) != 1 || len(rule.EnginesOf(named[0])) < 2 {
		t.Fatalf("want the one deep-nesting rule of several engines, got %d rules", len(named))
	}
	if named, found := rule.Named("DeepNestingDetector"); !found || named.Sin().Definition().Name != "deep-nesting" {
		t.Fatalf("DeepNestingDetector names no single rule")
	}
}

func TestARuleIsFoundByItsNameLeniently(t *testing.T) {
	for _, name := range []string{"deep-nesting", "DeepNestingDetector", "DeepNesting", "deepnesting"} {
		if found, named := rule.Named(name); !named || found.Sin().Definition().Name != "deep-nesting" {
			t.Errorf("%s names no single deep-nesting rule", name)
		}
	}
}

func TestANameGivenItsEngineMeansTheRuleJudgingThatEngine(t *testing.T) {
	for _, engine := range []catalog.Engine{catalog.PHP, catalog.Python, catalog.CSharp, catalog.Go} {
		named, found := rule.Named(string(engine) + "/DeepNestingDetector")
		if !found {
			t.Fatalf("%s/DeepNestingDetector means no rule", engine)
		}
		if !slices.Contains(rule.EnginesOf(named), engine) {
			t.Fatalf("%s/DeepNestingDetector means a rule that does not judge %s", engine, engine)
		}
	}
}
