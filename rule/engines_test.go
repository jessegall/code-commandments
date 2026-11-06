package rule_test

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

func TestOneRuleJudgesEveryEngineItNames(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/till.py": `def ship(lines):
    for line in lines:
        if line.paid:
            line.send()
`,
	})

	csharp := csharptest.FromSource(t, map[string]string{"Shop/Till.cs": `namespace Shop;

public sealed class Till
{
    public void Ship(string[] lines)
    {
        foreach (var line in lines)
        {
            if (line.Length > 0)
            {
                System.Console.WriteLine(line);
            }
        }
    }
}
`})

	written := `{"engine": ["python", "csharp"], "sin": {"name": "x", "description": "x", "skill": "php/absence"},
		"find": {"select": "loop", "where": [{"body": {"is": "branch"}}]}}`

	judging, err := rule.Parse("LoopWrappedInIf", []byte(written), shipped)
	if err != nil {
		t.Fatalf("a rule naming two engines is not read: %v", err)
	}

	if names := fmt.Sprint(judging.Engines()); names != "[python csharp]" {
		t.Errorf("the rule judges %s", names)
	}

	for language, codebase := range map[string]*engine.Codebase{"python": python, "csharp": csharp} {
		if found := len(judging.Find(codebase)); found != 1 {
			t.Errorf("the rule finds %d loops in the %s codebase, want 1", found, language)
		}
	}
}

func TestOneRuleReadsABodyTheSameInEveryLanguage(t *testing.T) {
	python := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/till.py": `def ship(lines):
    for line in lines:
        if line.paid:
            line.send()
`,
	})

	csharp := csharptest.FromSource(t, map[string]string{"Shop/Till.cs": `namespace Shop;

public sealed class Till
{
    public void Ship(string[] lines)
    {
        foreach (var line in lines)
        {
            if (line.Length > 0)
            {
                System.Console.WriteLine(line);
            }
        }
    }
}
`})

	for _, each := range []struct {
		step  string
		want  map[string]int
		holds string
	}{
		{
			step:  `{"body": {"is": "branch"}}`,
			want:  map[string]int{"python": 1, "csharp": 1},
			holds: "the body is read through whatever the language wraps it in, so both answer alike",
		},
		{
			step:  `{"child": {"is": "branch"}}`,
			want:  map[string]int{"python": 1, "csharp": 0},
			holds: "a child is a child: C# hangs a block between the loop and its branch, and that is its syntax rather than a fault",
		},
	} {
		written := `{"engine": ["python", "csharp"], "sin": {"name": "x", "description": "x", "skill": "php/absence"},
			"find": {"select": "loop", "where": [` + each.step + `]}}`

		judging, err := rule.Parse("LoopWrappedInIf", []byte(written), shipped)
		if err != nil {
			t.Fatalf("%s: %v", each.step, err)
		}

		for language, codebase := range map[string]*engine.Codebase{"python": python, "csharp": csharp} {
			if found := len(judging.Find(codebase)); found != each.want[language] {
				t.Errorf("%s finds %d in %s, want %d — %s", each.step, found, language, each.want[language], each.holds)
			}
		}
	}
}

func TestARuleNamingSeveralEnginesIsStillHeldToEachOfThem(t *testing.T) {
	for _, each := range []struct {
		why   string
		named string
		find  string
		said  string
	}{
		{
			why:   "a kind no engine the rule judges writes is refused",
			named: `["python", "csharp"]`,
			find:  `{"select": "kind:Stmt_Property"}`,
			said:  "Stmt_Property",
		},
		{
			why:   "an engine named twice is a slip",
			named: `["python", "python"]`,
			find:  `{"select": "loop"}`,
			said:  "named twice",
		},
		{
			why:   "an engine that is no engine says so, in a list as it does alone",
			named: `["python", "elixir"]`,
			find:  `{"select": "loop"}`,
			said:  "elixir",
		},
		{
			why:   "and an empty list names nothing to judge",
			named: `[]`,
			find:  `{"select": "loop"}`,
			said:  "names the engine it judges",
		},
	} {
		written := `{"engine": ` + each.named + `, "sin": {"name": "x", "description": "x", "skill": "php/absence"}, "find": ` + each.find + `}`

		_, err := rule.Parse("X", []byte(written), shipped)
		switch {
		case err == nil:
			t.Errorf("%s: the rule parsed", each.why)
		case !strings.Contains(err.Error(), each.said):
			t.Errorf("%s: refused with %v, which does not name %q", each.why, err, each.said)
		}
	}

	written := `{"engine": "python", "sin": {"name": "x", "description": "x", "skill": "php/absence"}, "find": {"select": "loop"}}`
	if _, err := rule.Parse("X", []byte(written), shipped); err != nil {
		t.Errorf("a rule naming one engine: %v", err)
	}
}
