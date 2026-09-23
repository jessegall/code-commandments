---
name: commandments-csharp-flow
description: "Shaping a C# method body — a null or precondition check at the start of a method, an `if` that decides whether the rest of the method runs, an `if`/`else if` chain, a loop whose whole body sits under one `if`, a block nested three deep, or an `else` after a branch that already returned or threw. Read this BEFORE writing any of them, and when a C# flow finding points here."
---

# C# flow — guard at the top, keep the body flat

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat
> and at the method's own indentation. Every condition you wrap the work in is another brace the
> reader has to hold open, so a method should read top to bottom: *here's what would stop us →
> here's the work.*

## The principle

### Guard at the top, then leave

Every precondition a method depends on — an argument that must be there, a state that must hold
— is checked **first**, and short-circuits with a `return` or a `throw`. By the time control
reaches the real work, everything it needs is guaranteed, so the work runs at the method's own
indentation with no `else` and no nesting. The shape documents the contract.

```csharp
public Booking Ship(Order? order)
{
    ArgumentNullException.ThrowIfNull(order);

    if (order.Lines.Count == 0)
    {
        return Booking.None;
    }

    var carrier = PickCarrier(order);

    return carrier.Book(order);
}
```

### No `else` after a branch that already left

When the `if` returns, throws, `continue`s or `break`s, the code after it only runs when the
condition was false — the `else` says nothing the exit did not already say, and it pushes the rest
of the method one level in. Drop the `else` and dedent.

### A loop body wrapped in one `if` is a `continue` guard

`foreach (var line in lines)` followed by an `if` holding the entire body is the same shape inside
a loop: flip the condition, `continue`, and let the body sit at the loop's level — or, when the
loop only filters, let `Where` say so.

### An `else if` ladder over one subject is a dispatch

`if (status == Status.Paid) … else if (status == Status.Late) … else if (status == Status.Void) …`
tests one value against case after case. That is a closed set: answer it with a `switch`
expression, whose exhaustiveness the compiler checks, or — when each case carries behaviour — put
the behaviour on the type (a method on each subclass, or an extension on the enum). The ladder
re-tests the subject on every rung and grows a rung per case forever.

### Depth is the symptom

Every `if`, loop and `switch` is one more choice the reader holds open; an `else if` is a rung of
the same choice, and a `try`, a `using` or a `lock` is a boundary, not a choice. Four choices deep
— a loop in a loop in an `if` in a loop — means a decision is buried inside another decision.
Guard the outer one away (`continue` past what does not apply), let LINQ do the inner iteration,
or extract the inner block into a method named for what it decides.

### What is NOT this sin

- An `if`/`else` where neither branch leaves and both are real work of equal weight — a decision,
  not a guard.
- A `try`/`catch`, `using` or `lock` the work genuinely runs inside; that nesting is the failure,
  resource or concurrency boundary, not a buried condition.
- A `switch` on a type or a pattern — `shape switch { Circle c => …, Square s => … }` — which is
  already the dispatch this skill asks for.

## Related skills

- [`backend/guard-clauses-and-flow`](../../backend/guard-clauses-and-flow/SKILL.md) — the same discipline on the PHP backend.
- [`python/flow`](../../python/flow/SKILL.md) — the same discipline in Python.
