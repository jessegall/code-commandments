package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestAConditionIsAConceptOnlyWhenItAsksSeveralObjects holds the shared reading of an unnamed condition where C#
// files its own parts: `&&` and `||` as expressions of their own, a member read under `expression`, and a local
// declaration as the statement that may stand above a guard.
func TestAConditionIsAConceptOnlyWhenItAsksSeveralObjects(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"three parts over two objects": {
			"namespace Shop;\n\npublic sealed class Floor\n{\n    public void Tick(Event e, Slot slot)\n    {\n        if (e.Late && slot.Open && e.Counted > 0)\n        {\n            slot.Close();\n        }\n    }\n}\n\npublic sealed class Event { public bool Late { get; set; } public int Counted { get; set; } }\n\npublic sealed class Slot { public bool Open { get; set; } public void Close() { } }\n",
			1,
		},
		"a chain of calls over two objects": {
			"namespace Shop;\n\npublic sealed class Ship\n{\n    public void Go(Order order, Slot slot)\n    {\n        if (order.IsPaid() && order.HasLines() && slot.Free())\n        {\n            order.Send(slot);\n        }\n    }\n}\n\npublic sealed class Order { public bool IsPaid() => true; public bool HasLines() => true; public void Send(Slot slot) { } }\n\npublic sealed class Slot { public bool Free() => true; }\n",
			1,
		},
		"three parts over one object": {
			"namespace Shop;\n\npublic sealed class Within\n{\n    public bool Covers(Window window, int at)\n    {\n        if (window.Start <= at && window.End >= at && window.Open)\n        {\n            return true;\n        }\n\n        return false;\n    }\n}\n\npublic sealed class Window { public int Start { get; set; } public int End { get; set; } public bool Open { get; set; } }\n",
			0,
		},
		"a guard standing at the top": {
			"namespace Shop;\n\npublic sealed class Guarded\n{\n    public void Go(Order order, Slot slot)\n    {\n        if (!order.IsPaid() || !slot.Free() || order.Held)\n        {\n            return;\n        }\n\n        order.Send(slot);\n    }\n}\n\npublic sealed class Order { public bool Held { get; set; } public bool IsPaid() => true; public void Send(Slot slot) { } }\n\npublic sealed class Slot { public bool Free() => true; }\n",
			0,
		},
		"a constant read off a type is no object": {
			"namespace Shop;\n\npublic enum Mode { Auto, Manual }\n\npublic sealed class Picker\n{\n    public void Pick(Mode mode, int at)\n    {\n        if (mode == Mode.Auto && at > 0 && at < 10)\n        {\n            System.Console.WriteLine(at);\n        }\n    }\n}\n",
			0,
		},
		"a part that binds a name the body uses": {
			"namespace Shop;\n\nusing System.Collections.Generic;\n\npublic sealed class Lookup\n{\n    public bool Named(Dictionary<string, string> by, string name, Slot slot)\n    {\n        if (!string.IsNullOrEmpty(name) && by.TryGetValue(name, out var found) && slot.Takes(found))\n        {\n            return true;\n        }\n\n        return false;\n    }\n}\n\npublic sealed class Slot { public bool Takes(string what) => what.Length > 0; }\n",
			0,
		},
		"a chain the method returns": {
			"namespace Shop;\n\npublic sealed class Told\n{\n    public bool Shippable(Order order, Slot slot) => order.IsPaid() && order.HasLines() && slot.Free();\n}\n\npublic sealed class Order { public bool IsPaid() => true; public bool HasLines() => true; }\n\npublic sealed class Slot { public bool Free() => true; }\n",
			0,
		},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := csharptest.FromSource(t, map[string]string{"Shop/Thing.cs": test.source})
			found := cs.In(codebase).
				WhereIs(engine.Conjunction).
				Where(engine.TestsAnUnnamedConcept).
				Count()

			if found != test.want {
				t.Errorf("found %d, want %d", found, test.want)
			}
		})
	}
}
