package rule

import (
	"testing"
)

func TestATraceReadsTheRelationsItsFindBuildsAsAJudgeDoes(t *testing.T) {
	codebase := expected(t, "flags", "php")
	probe := parsed(t, []byte(`{
		"$schema": "commandments:neutral-rule/v2",
		"engine": ["php"],
		"sin": {"name": "traced-relation", "skill": "php/flow", "description": "a call the relation lists", "rule": "probe", "suggestion": "probe"},
		"find": {
			"relations": {"call-lines": {"from": "call", "as": "c", "pipeline": [{"project": {"line": "$c.line"}}]}},
			"select": "call",
			"as": "k",
			"having": ["hasRow", "call-lines", ["record", "line", "$k.line"]],
			"emit": "$k"
		}
	}`))

	traced, err := probe.Trace(codebase, "php.php", 8, 8)
	if err != nil {
		t.Fatal(err)
	}

	if len(traced) == 0 {
		t.Fatal("the trace asked no call on line 8")
	}
	for _, step := range traced[0].Steps {
		if step.Part == "having" && step.Answered != "true" {
			t.Errorf("the trace answered the relation read %s, not true as the judge does", step.Answered)
		}
	}
}
