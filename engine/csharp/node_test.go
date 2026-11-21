package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

const cart = `using System.Collections.Generic;

namespace Shop;

public enum Status { Open, Paid }

public sealed class Cart
{
    private readonly List<string>? lines;

    public string Name { get; } = "cart";

    public int Count(Status status) => status == Status.Paid ? 0 : lines!.Count;

    public void Clear()
    {
        if (lines is null) return;
        lines.Clear();
    }
}
`

func TestTheStatementsOfABlockAreStatements(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Cart.cs": cart})
	statements := csharp.In(codebase).WhereStatement().Count()
	if statements != 4 {
		t.Errorf("found %d statements, not the block, the if, its return and the call", statements)
	}
}

const ledger = `namespace Shop;

public sealed class Ledger
{
    public int Total => 3;

    public int Count { get; }

    public int Sum() => Total + Count;
}
`

