package python_test

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine/python"
)

func TestEveryFieldFlowsAsThePHPEngineSays(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string]map[string][2]int
	golden(t, "attribute-flow", &want)
	seen := 0
	for _, match := range codebase.WhereClass().Get() {
		class := python.Node{Match: match}
		key := match.Node().Symbol + "@" + place(class)
		answer, ok := want[key]
		if !ok {
			t.Errorf("%s: no class in the golden answers", key)
			continue
		}
		seen++
		got := map[string][2]int{}
		for _, field := range class.FieldNames() {
			verdict := codebase.Program.AttributeFlow(class, field)
			got[field] = [2]int{verdict.Assume, verdict.Guard}
		}
		if a, b := marshal(got), marshal(answer); a != b {
			t.Errorf("%s: %s, not %s", key, a, b)
		}
	}
	if seen != len(want) {
		t.Errorf("%d classes, not %d", seen, len(want))
	}
}

func marshal(value any) string {
	raw, _ := json.Marshal(value)

	return string(raw)
}
