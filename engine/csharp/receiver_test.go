package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

func sentTo(t *testing.T, codebase *engine.Codebase, kind string) engine.Match {
	t.Helper()
	for _, node := range codebase.WhereKind(kind).Get() {
		return node.Receiver()
	}

	t.Fatalf("no %s in the sources", kind)

	return engine.Match{}
}

func TestWhatACSharpNodeIsSentToIsTheObject(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Till.cs": `namespace Shop;

public sealed class Till
{
    public void Open(Slot slot)
    {
        slot.Close();
    }

    public bool Free(Slot slot)
    {
        return slot.Open;
    }

    public void Again(Slot slot)
    {
        Open(slot);
    }
}

public sealed class Slot
{
    public bool Open { get; set; }

    public void Close() { }
}
`})

	if called := sentTo(t, codebase, "InvocationExpression"); called.Written() != "slot" {
		t.Errorf("a method call is sent to %q, and it is sent to the object", called.Written())
	}

	if read := sentTo(t, codebase, "SimpleMemberAccessExpression"); read.Written() != "slot" {
		t.Errorf("a member read is sent to %q", read.Written())
	}

	var bare engine.Match
	for _, call := range codebase.WhereKind("InvocationExpression").Get() {
		if call.CalleeName() == "Open" && call.Receiver().Written() != "" {
			bare = call
		}
	}

	if bare.Exists() {
		t.Errorf("a call of a bare name is sent to %q", bare.Receiver().Written())
	}
}

func TestWhatACSharpCallCallsIsItsMethod(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Round.cs": `namespace Shop;

public sealed class Round
{
    public void Go(Slot slot)
    {
        slot.Close();
        Again();
    }

    public void Again() { }
}

public sealed class Slot
{
    public void Close() { }
}
`})

	var named []string
	for _, call := range codebase.WhereKind("InvocationExpression").Get() {
		named = append(named, call.CalleeName())
	}

	if len(named) != 2 || named[0] != "Close" || named[1] != "Again" {
		t.Errorf("the calls call %v, and they call Close and Again", named)
	}
}

func TestACallThroughAConditionalAccessIsSentToTheObjectBeforeIt(t *testing.T) {
	codebase := csharptest.FromSource(t, map[string]string{"Shop/Maybe.cs": `namespace Shop;

public sealed class Maybe
{
    public void Go(Slot? slot)
    {
        slot?.Close();
    }
}

public sealed class Slot
{
    public void Close() { }
}
`})

	if called := sentTo(t, codebase, "InvocationExpression"); called.Written() != "slot" {
		t.Errorf("a call through a null-conditional access is sent to %q", called.Written())
	}
}
