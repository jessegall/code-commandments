package rule_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

// Whether two nodes name the SAME thing is the one question a rule could not ask, and it is what a whole family of
// shipped rules turns on: changing the collection a loop walks is a sin, and changing another one in the same loop
// is not. These hold the pair, in the two languages where the loop's collection sits in a differently named field of
// the tree, since what a rule says is the path and not the spelling.

// TestARuleAsksWhetherTwoNodesNameTheSameThing holds it over Python: a remove on the very list its loop walks is
// kept, and the same call on another list of the same class, in the same loop, is not.
func TestARuleAsksWhetherTwoNodesNameTheSameThing(t *testing.T) {
	module := pythontest.FromSource(t, map[string]string{"till.py": `class Till:
    def __init__(self) -> None:
        self.lines: list[str] = []
        self.spare: list[str] = []

    def drop(self, bad: list[str]) -> None:
        for line in self.lines:
            if line in bad:
                self.lines.remove(line)

    def spill(self, bad: list[str]) -> None:
        for line in self.lines:
            if line in bad:
                self.spare.remove(line)
`})

	walked := `{"sameAs": {"of": "closest:loop", "target": "child:iter"}, "of": "receiver"}`

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"name": "remove"}, ` + walked + `]}`:          "[9]",
		`{"select": "call", "where": [{"name": "remove"}, {"not": ` + walked + `}]}`: "[14]",
		// Without the identity check the rule cannot tell the two apart, which is the whole point of it.
		`{"select": "call", "where": [{"name": "remove"}, {"withinLoop": true}]}`: "[9 14]",
	} {
		if got := found(t, "python", query, module); got != want {
			t.Errorf("python %s: found %s, want %s", query, got, want)
		}
	}
}

// TestTheSameQuestionAnswersForCSharp holds it where the collection a foreach walks is another field of the tree, so
// a rule names its own language's field and everything else about the check is the same.
func TestTheSameQuestionAnswersForCSharp(t *testing.T) {
	project := csharptest.FromSource(t, map[string]string{"Till.cs": `using System.Collections.Generic;

namespace Shop;

class Till
{
    private readonly List<string> lines = new();
    private readonly List<string> spare = new();

    public void Drop(List<string> bad)
    {
        foreach (var line in lines)
        {
            if (bad.Contains(line))
            {
                lines.Remove(line);
            }
        }
    }

    public void Spill(List<string> bad)
    {
        foreach (var line in lines)
        {
            if (bad.Contains(line))
            {
                spare.Remove(line);
            }
        }
    }
}
`})

	walked := `{"sameAs": {"of": "closest:loop", "target": "child:Expression"}, "of": "receiver"}`

	for query, want := range map[string]string{
		`{"select": "call", "where": [{"name": "Remove"}, ` + walked + `]}`:          "[16]",
		`{"select": "call", "where": [{"name": "Remove"}, {"not": ` + walked + `}]}`: "[27]",
	} {
		if got := found(t, "csharp", query, project); got != want {
			t.Errorf("csharp %s: found %s, want %s", query, got, want)
		}
	}
}

// TestAPathThatNamesNoTargetSaysWhy holds both halves of the path to the targets a rule may follow, since a step
// that silently answers nothing is a rule that silently finds nothing.
func TestAPathThatNamesNoTargetSaysWhy(t *testing.T) {
	for query, reason := range map[string]string{
		`{"select": "call", "where": [{"sameAs": {"of": "sideways"}}]}`:                          `sameAs "sideways"`,
		`{"select": "call", "where": [{"sameAs": {"of": "parent", "target": "theLoopsThing"}}]}`: `target "theLoopsThing"`,
	} {
		written := `{"engine": "python", "sin": {"name": "x", "skill": "python/absence"}, "find": ` + query + `}`

		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want %q", query, err, reason)
		}
	}
}
