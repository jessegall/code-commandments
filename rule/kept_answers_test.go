package rule

import (
	"encoding/json"
	"testing"
)

func TestADefinitionReadingTheWholeTreeIsAskedOnceForTheSameArguments(t *testing.T) {
	var defined map[string]definition
	if err := json.Unmarshal([]byte(`{
		"callsOf": {"args": ["d"], "expr": ["count", ["referrers", "$d"]]},
		"callsOfCallee": {"args": ["d"], "expr": ["callsOf", "$d"]},
		"plain": {"args": ["d"], "expr": ["count", "$d"]}
	}`), &defined); err != nil {
		t.Fatal(err)
	}
	var calling, plain Expr
	if err := json.Unmarshal([]byte(`["callsOfCallee", "$x"]`), &calling); err != nil {
		t.Fatal(err)
	}
	if err := json.Unmarshal([]byte(`["plain", "$x"]`), &plain); err != nil {
		t.Fatal(err)
	}
	asked := evaluation{definitions: defined, kept: keepingAnswers(defined)}
	before := answersAsked.Load()

	for range 30 {
		asked.evaluate(calling, bindings{}.with("x", known("Shop\\Order::total")))
		asked.evaluate(plain, bindings{}.with("x", known("Shop\\Order::total")))
	}
	asked.evaluate(calling, bindings{}.with("x", known("Shop\\Order::pay")))

	if did := answersAsked.Load() - before; did != 4 {
		t.Errorf("thirty asks of one callee and one of another asked the definitions %d times, not once for each definition and callee", did)
	}
}
