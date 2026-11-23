package rule

import (
	"encoding/json"
	"testing"
)

func TestADefinitionIsAskedOnceForTheSameArguments(t *testing.T) {
	var defined map[string]definition
	if err := json.Unmarshal([]byte(`{
		"callsOf": {"args": ["d"], "expr": ["count", ["referrers", "$d"]]},
		"callsOfCallee": {"args": ["d"], "expr": ["callsOf", "$d"]},
		"plain": {"args": ["d"], "expr": ["count", "$d"]},
		"keptPlain": {"args": ["d"], "expr": ["count", "$d"], "kept": true}
	}`), &defined); err != nil {
		t.Fatal(err)
	}
	var calling, plain, keptPlain Expr
	if err := json.Unmarshal([]byte(`["callsOfCallee", "$x"]`), &calling); err != nil {
		t.Fatal(err)
	}
	if err := json.Unmarshal([]byte(`["plain", "$x"]`), &plain); err != nil {
		t.Fatal(err)
	}
	if err := json.Unmarshal([]byte(`["keptPlain", "$x"]`), &keptPlain); err != nil {
		t.Fatal(err)
	}
	asked := evaluation{definitions: defined, kept: keepingAnswers(defined)}
	before := answersAsked.Load()

	for range 30 {
		asked.evaluate(calling, bindings{}.with("x", known("Shop\\Order::total")))
		asked.evaluate(plain, bindings{}.with("x", known("Shop\\Order::total")))
		asked.evaluate(keptPlain, bindings{}.with("x", known("Shop\\Order::total")))
	}
	asked.evaluate(calling, bindings{}.with("x", known("Shop\\Order::pay")))

	if did := answersAsked.Load() - before; did != 7 {
		t.Errorf("thirty asks of one callee and one of another asked the definitions %d times, not once for each kept definition and callee and twice for the plain one", did)
	}
}

func TestAnAnswerAskedOnceIsNotHeld(t *testing.T) {
	var defined map[string]definition
	if err := json.Unmarshal([]byte(`{"plain": {"args": ["d"], "expr": ["count", "$d"]}}`), &defined); err != nil {
		t.Fatal(err)
	}
	var plain Expr
	if err := json.Unmarshal([]byte(`["plain", "$x"]`), &plain); err != nil {
		t.Fatal(err)
	}
	kept := keepingAnswers(defined)
	asked := evaluation{definitions: defined, kept: kept}

	for at := range 10000 {
		asked.evaluate(plain, bindings{}.with("x", known(float64(at))))
	}

	if held := kept.held.Load(); held != 0 {
		t.Errorf("ten thousand questions, each asked once, held %d answers, where an answer is held from the second time it is asked", held)
	}
}

func TestNoMoreAnswersAreHeldThanTheLimit(t *testing.T) {
	var defined map[string]definition
	if err := json.Unmarshal([]byte(`{"plain": {"args": ["d"], "expr": ["count", "$d"]}}`), &defined); err != nil {
		t.Fatal(err)
	}
	var plain Expr
	if err := json.Unmarshal([]byte(`["plain", "$x"]`), &plain); err != nil {
		t.Fatal(err)
	}
	kept := keepingAnswers(defined)
	kept.held.Store(mostAnswersHeld)
	asked := evaluation{definitions: defined, kept: kept}
	before := answersAsked.Load()

	for range 4 {
		asked.evaluate(plain, bindings{}.with("x", known("Shop\\Order::total")))
	}

	if did := answersAsked.Load() - before; did != 4 {
		t.Errorf("with the limit of held answers reached, four asks worked the answer out %d times, not four: an answer was held past the limit", did)
	}
}
