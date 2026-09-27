package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

const orders = `using System;

namespace Shop;

public sealed class Order { }

public sealed class Orders
{
    public Order? Find(int id) => null;

    public void Ship(int id)
    {
        var forced = Find(id)!;
        var thrown = Find(id) ?? throw new InvalidOperationException();
        var tested = Find(id);
        if (tested is null) throw new InvalidOperationException();
        var kept = Find(id);
    }
}
`

// TestTheCallsToAMethodCountThoseThatAssertItsResultIsThere holds CallsTo to every call outside the tests, and to
// those that force its result, throw when it is missing or test the local it lands in only to throw.
func TestTheCallsToAMethodCountThoseThatAssertItsResultIsThere(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Orders.cs": orders})
	find := csharptest.First(t, codebase, "Shop/Orders.cs", "MethodDeclaration")
	if find.Name() != "Find" {
		t.Fatalf("the first method is %s", find.Name())
	}
	calls := csharp.Of(codebase).CallsTo(find)
	if calls.OutsideTests != 4 || calls.Asserting != 3 {
		t.Errorf("Find has %d calls outside the tests, %d asserting; want 4, 3", calls.OutsideTests, calls.Asserting)
	}
}
