package judge

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestAWatchedRunSaysWhatItFound(t *testing.T) {
	findings := []engine.Finding{
		{Detector: "SentenceMethodNameDetector", Skill: "python/method-mood", Sin: "sentence-method-name",
			File: "/project/src/support/typed_lines.py", Location: "/project/src/support/typed_lines.py:52", Scope: "FunctionDef oldest_line_at"},
		{Detector: "UnnamedConditionDetector", Skill: "python/flow", Sin: "unnamed-condition",
			File: "/project/src/part.py", Location: "/project/src/part.py:130", Scope: "`if settings.auto and not self.said:`"},
		{Detector: "DictBagDetector", Skill: "python/value-objects", Sin: "dict-bag",
			File: "/project/src/labels.py", Location: "/project/src/labels.py:10", Scope: "FunctionDef label"},
		{Detector: "DictBagDetector", Skill: "python/value-objects", Sin: "dict-bag",
			File: "/project/src/labels.py", Location: "/project/src/labels.py:10", Scope: "FunctionDef label"},
	}

	said := NewReport("/project", findings, nil, nil, Skipped{}).Saying(map[string]string{
		"sentence-method-name": "A sentence used as a method name.",
		"unnamed-condition":    "Three parts over two objects — the reader works out what the condition MEANS instead of reading it.",
		"dict-bag":             "A parameter typed as a dict read by string keys, which is a record nobody declared, threaded through function after function.",
	}).Said()

	lines := strings.Split(said, "\n")
	want := []string{
		"part.py, line 130: three parts over two objects",
		"typed_lines.py, line 52: oldest_line_at, a sentence used as a method name",
		"labels.py, line 10: label, a parameter typed as a dict read by string keys, which is a record… ×2",
		"",
		"4 sins in 3 files",
	}

	for at, line := range want {
		if at >= len(lines) {
			t.Errorf("line %d is missing, want %q", at+1, line)

			continue
		}

		if lines[at] != line {
			t.Errorf("line %d reads %q, want %q", at+1, lines[at], line)
		}
	}

	if len(lines) != len(want) {
		t.Errorf("the card reads %d lines, want %d: %q", len(lines), len(want), said)
	}

	if strings.ContainsAny(said, "\033") {
		t.Errorf("a card's lines carry terminal codes: %q", said)
	}
}
