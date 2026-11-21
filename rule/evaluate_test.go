package rule

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

// expected is the codebase of one expected tree of the contract samples, its sources read beside it.
func expected(t *testing.T, sample, tree string) *engine.Codebase {
	t.Helper()
	folder := filepath.Join("..", "contract", "samples", "testdata", "expected", sample)
	stream, err := os.ReadFile(filepath.Join(folder, tree+".jsonl"))
	if err != nil {
		t.Fatal(err)
	}
	sources := map[string]string{}
	beside, _ := os.ReadDir(folder)
	for _, each := range beside {
		if text, err := os.ReadFile(filepath.Join(folder, each.Name())); err == nil && filepath.Ext(each.Name()) != ".jsonl" {
			sources["/expected/"+sample+"/"+each.Name()] = string(text)
		}
	}
	codebase, err := engine.FromString(string(stream), sources)
	if err != nil {
		t.Fatal(err)
	}

	return codebase
}

func parsed(t *testing.T, text []byte) Rule {
	t.Helper()
	read, err := Parse("probe", text, anySkill)
	if err != nil {
		t.Fatal(err)
	}

	return read
}

func TestEvaluateTheSection18RuleOnTheExpectedHandlerTrees(t *testing.T) {
	text, err := os.ReadFile("testdata/swallow-catch.json")
	if err != nil {
		t.Fatal(err)
	}
	swallow := parsed(t, text)

	for _, each := range []struct {
		sample, tree string
		lines        []int
	}{
		{"bare-except-pass", "python", []int{4}},
		{"specific-except-empty", "python", []int{4}},
		{"shadowed-exception", "python", []int{8}},
		{"pass-and-comment", "python", []int{4}},
		{"flags", "python", []int{11}},
		{"filtered-catch", "csharp", []int{11}},
		{"filtered-empty-catch", "csharp", []int{13}},
		{"handler-single-exit", "php", []int{7, 17}},
		{"test-catch-resolved", "ReaderTest", []int{13}},
		{"test-catch-unresolved", "TotalTest", []int{11}},
		{"log-then-fallback", "python", nil},
		{"log-then-fallback", "typescript", nil},
		{"selectors", "php", nil},
		{"selectors", "python", nil},
		{"selectors", "typescript", nil},
		{"selectors", "csharp", nil},
	} {
		found := swallow.Find(expected(t, each.sample, each.tree))
		var lines []int
		for _, match := range found {
			lines = append(lines, match.Line())
		}
		if len(lines) != len(each.lines) {
			t.Errorf("%s/%s: the rule found lines %v, and %v are its swallowed handlers", each.sample, each.tree, lines, each.lines)

			continue
		}
		for at := range lines {
			if lines[at] != each.lines[at] {
				t.Errorf("%s/%s: the rule found lines %v, and %v are its swallowed handlers", each.sample, each.tree, lines, each.lines)

				break
			}
		}
	}
}

func TestEvaluateANegatedUnresolvedComparisonFindsNothing(t *testing.T) {
	probe := parsed(t, written(`"find":{"select":"handler","as":"site","where":["not",["assignable","$site.binding.resolved","App\\Missing"]]}`))
	judged := probe.Judge(expected(t, "test-catch-unresolved", "TotalTest"))
	if len(judged.Found) != 0 || len(judged.Undecided) != 1 {
		t.Errorf("a negated unknown found %d and left %d undecided", len(judged.Found), len(judged.Undecided))
	}
}

func TestEvaluateACaseAsksOnlyTheArmItSelects(t *testing.T) {
	probe := parsed(t, written(`"find":{"select":"handler","as":"site","where":["case","$site.kind",{"handler":true,"if":["exists","$site.condition"]},false]}`))
	if found := probe.Find(expected(t, "bare-except-pass", "python")); len(found) != 1 {
		t.Errorf("the handler arm found %d handlers", len(found))
	}
}

func TestTruthOfAConjunctionIsDecidedByAFalseOperandOverAnUnknownOne(t *testing.T) {
	falseWins := parsed(t, written(`"find":{"select":"handler","as":"site","where":["or",["and",["assignable","$site.binding.resolved","X"],false],["exists","$site.body"]]}`))
	if found := falseWins.Find(expected(t, "bare-except-pass", "python")); len(found) != 1 {
		t.Errorf("unknown and false, or true, found %d handlers", len(found))
	}
}

func TestTruthOfAQuantifierOverAnEmptyList(t *testing.T) {
	for where, wanted := range map[string]int{
		`["every","$site.caughtTypes","c",false]`: 1,
		`["some","$site.caughtTypes","c",true]`:   0,
		`["none","$site.caughtTypes","c",true]`:   1,
	} {
		probe := parsed(t, written(`"find":{"select":"handler","as":"site","where":`+where+`}`))
		if found := probe.Find(expected(t, "bare-except-pass", "python")); len(found) != wanted {
			t.Errorf("%s over a bare except found %d handlers, and %d is right", where, len(found), wanted)
		}
	}
}
