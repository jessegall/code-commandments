package rule

import (
	"reflect"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/skill"
)

func TestEveryCheckSaysWhetherItIsAnsweredFromItsFile(t *testing.T) {
	written := jsonKeys(reflect.TypeFor[Step]())

	for _, key := range written {
		fromTheFile := slices.Contains(answeredFromTheFile, key)
		beyond := slices.Contains(readAcrossFiles, key)

		if fromTheFile && beyond {
			t.Errorf("%q is in both lists; a check is answered from its file or it is not", key)
		}

		if !fromTheFile && !beyond {
			t.Errorf("%q is in neither list: say whether it can be answered from the file it reports in", key)
		}
	}

	for _, key := range append(slices.Clone(answeredFromTheFile), readAcrossFiles...) {
		if !slices.Contains(written, key) {
			t.Errorf("the lists name %q, which no step writes", key)
		}
	}
}

func TestARuleSaysWhetherItReadsBeyondTheFileItReportsIn(t *testing.T) {
	for written, across := range map[string]bool{
		`{"select": "type-declaration", "where": [{"fileMatches": "/migrations/"}, {"nameLike": "*Migration"}]}`:        false,
		`{"select": "type-declaration", "where": [{"extends": "Migration"}]}`:                                           false,
		`{"select": "type-declaration", "where": [{"extendsAny": "Migration"}]}`:                                        true,
		`{"select": "type-declaration", "where": [{"not": {"extendsAny": "Migration"}}]}`:                               true,
		`{"select": "type-declaration", "where": [{"any": [{"name": "x"}, {"implements": "Lifecycle"}]}]}`:              true,
		`{"select": "function", "where": [{"count": {"descendant": {"unused": true}, "atLeast": 1}}]}`:                  true,
		`{"select": "type-declaration", "where": [{"members": {"is": "function", "atLeast": 2}}]}`:                      false,
		`{"select": "type-declaration", "where": [{"members": {"inherited": true, "atLeast": 20}}]}`:                    true,
		`{"select": "member-access", "where": [{"name": "total", "of": "receiver"}]}`:                                   false,
		`{"select": "member-access", "where": [{"file": "app/Models/**", "of": "declaredBy"}]}`:                         true,
		`{"select": "type-declaration", "where": [{"nameLike": "*Migration"}], "reject": [{"callers": {"atMost": 0}}]}`: true,
	} {
		rule, err := Parse("LayoutDetector", []byte(`{"engine": "python", "sin": {"name": "helper-in-a-kind-folder", "description": "A class sits in a folder of another kind", "skill": "python/absence"}, "find": `+written+`}`), skill.Slugged)
		if err != nil {
			t.Fatalf("%s: %v", written, err)
		}

		if got := rule.ReadsAcrossFiles(); got != across {
			t.Errorf("%s: reads across files %v, want %v", written, got, across)
		}

		if got := catalog.IsCrossFile(rule); got != across {
			t.Errorf("%s: the catalog reads it as cross-file %v, want %v", written, got, across)
		}
	}
}

func TestACallFromAnotherFileIsAnsweredWrongWhenTheFileIsReadAlone(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/pricing.py": `def total(prices: list[int]) -> int:
    return sum(prices)
`,
		"shop/checkout.py": `from shop.pricing import total


def pay(prices: list[int]) -> int:
    return total(prices)
`,
	})

	rule, err := Parse("UncalledFunctionDetector", []byte(`{"engine": "python", "sin": {"name": "uncalled-function", "description": "A function nothing calls", "skill": "python/absence"}, "find": {"select": "function", "where": [{"fileMatches": "pricing\\.py$"}, {"unused": true}]}}`), skill.Slugged)
	if err != nil {
		t.Fatal(err)
	}

	if found := rule.Find(codebase); len(found) > 0 {
		t.Fatalf("given every file, the rule reports %v; checkout.py calls total", locations(found))
	}

	alone, found := fileNamed(codebase, "pricing.py")
	if !found {
		t.Fatal("the scan read no pricing.py")
	}

	if found := rule.Find(alone); len(found) == 0 {
		t.Fatal("given the file alone the rule answers the same, so this is no longer the measurement it claims to be")
	}

	if !rule.ReadsAcrossFiles() {
		t.Error("the rule answers differently when shown one file and does not say it reads across files")
	}
}

func fileNamed(codebase *engine.Codebase, name string) (*engine.Codebase, bool) {
	for _, file := range codebase.Files() {
		if strings.HasSuffix(file.File.Path, name) {
			return file.Alone(), true
		}
	}

	return nil, false
}

func locations(found []engine.Match) []string {
	var places []string
	for _, match := range found {
		places = append(places, match.Location())
	}

	return places
}

func TestARuleSaysWhetherItReadsOnlyForward(t *testing.T) {
	for written, forward := range map[string]bool{
		`{"select": "type-declaration", "where": [{"extendsAny": "Migration"}]}`:                                    true,
		`{"select": "call", "where": [{"targets": "os.ReadDir"}]}`:                                                  true,
		`{"select": "function", "where": [{"unused": true}]}`:                                                       false,
		`{"select": "function", "where": [{"not": {"callers": {"atLeast": 2}}}]}`:                                   false,
		`{"select": "type-declaration", "where": [{"extendsAny": "Model"}, {"referredFrom": {"is": "parameter"}}]}`: false,
	} {
		rule, err := Parse("ProbeDetector", []byte(`{"engine": "python", "sin": {"name": "probe", "description": "probe", "skill": "python/absence"}, "find": `+written+`}`), skill.Slugged)
		if err != nil {
			t.Fatalf("%s: %v", written, err)
		}
		if rule.ReadsForward() != forward {
			t.Errorf("%s reads forward: %v, want %v", written, rule.ReadsForward(), forward)
		}
	}
}
