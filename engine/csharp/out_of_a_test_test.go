package csharp_test

import (
	"testing"

	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestAnOutBoundByATestIsForgivenOnlyWhereTheTestGuardsIt holds the one shape where forgiving null is not the
// author's claim: a method that answers true exactly when it filled its out parameter, read in the branch that
// answer guards. A framework annotating the parameter narrows it for the compiler, so a `!` there says the
// annotation is missing, which is a fix in the declaration and not at the call.
func TestAnOutBoundByATestIsForgivenOnlyWhereTheTestGuardsIt(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{
		"Labels.cs": `using System.Collections.Generic;

namespace Shop.Labels;

public sealed class Labels
{
    private readonly Dictionary<string, string> stored = [];

    private string? loose;

    public string Guarded(string id)
    {
        if (stored.TryGetValue(id, out var label))
        {
            return label!.Trim();
        }

        return "";
    }

    public string Negated(string id)
    {
        if (!stored.TryGetValue(id, out var label))
        {
            return label!.Trim();
        }

        return "";
    }

    public string Unguarded(string id)
    {
        stored.TryGetValue(id, out var label);

        return label!.Trim();
    }

    public string Own()
    {
        return loose!.Trim();
    }
}
`,
	})

	forgiven := map[int]bool{}
	for _, match := range cs.In(codebase).WhereExpression().Get() {
		node := cs.Node{Match: match}
		if node.ForgivesNull() {
			forgiven[match.Line()] = node.ForgivesAnOutOfATest()
		}
	}

	if len(forgiven) != 4 {
		t.Fatalf("%d forgiven reads, by line %v", len(forgiven), forgiven)
	}

	for line, pardoned := range forgiven {
		switch {
		case line == 15 && !pardoned:
			t.Error("the read in the branch the test guards is not taken as bound by it")
		case line != 15 && pardoned:
			t.Errorf("the read at line %d is taken as bound by a test that does not guard it", line)
		}
	}
}
