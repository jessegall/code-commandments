---
name: commandments-go-flow
description: "Shaping a Go function body: an error check at the top, an `if` that decides whether the rest of the function runs, an if/else-if chain over one value, a `for` whose whole body sits under one `if`, a block four choices deep, or an `else` after a branch that already returned. Read this BEFORE writing any of them, and when a Go flow finding points here."
---

# Go flow — guard at the top, keep the body flat

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Decide what would stop you first, at the top, and leave. What is left is the work, flat and at the
> function's own indentation. Go writes the guard as its own idiom — `if err != nil { return err }` is
> the first thing in half the functions ever written in it — so a function reads top to bottom: here is
> what would stop us, here is the work.

## The principle

### Guard at the top, then leave

Every precondition a function depends on is checked first and leaves: `return`, `break`, `continue`.
By the time the work runs, everything it needs is there, so it sits at the function's own
indentation with no `else` and no nesting.

```go
func ship(order *Order) error {
	if order == nil {
		return ErrNoOrder
	}
	if len(order.Lines) == 0 {
		return nil
	}

	return pickCarrier(order).Book(order)
}
```

Go's own `if err != nil { return err }` is this shape, and it is why a Go function has no business
being four choices deep: the language already hands you the way out.

### No `else` after a branch that already left

When the `if` returns, breaks, continues or panics, what follows it only runs when the condition was
false. The `else` says nothing the exit did not already say, and it indents the rest of the function
for nothing. Drop it and dedent.

### A loop body wrapped in one `if` is a `continue` guard

A `for` whose whole body is one `if` is the same shape inside a loop. Flip the condition, `continue`,
and let the body sit at the loop's own level.

### An if/else-if ladder over one subject is a switch

`if kind == "paid" { … } else if kind == "late" { … }` tests one value case after case. Go has a
`switch` for exactly that, and a defined type with `iota` constants for the set of cases, so the
ladder is both longer and less safe than what the language offers.

**No rule enforces this one.** A rule for it was written and cut: it found the ladder nowhere in four
real codebases and about two thousand Go files, because Go's own `switch` is easier to write than the
ladder is. It is here because the advice holds and because the habit travels in from languages whose
`switch` is worse, not because anything will tell you off.

### Depth is the symptom

Every `if`, `for`, `switch` and `select` is one more choice the reader holds open. Four deep means a
decision is buried inside another decision: guard the outer one away, let the inner iteration be a
function of its own, or name the inner block for what it decides.

## Related skills

- [`python/flow`](../../python/flow/SKILL.md) — the same discipline in Python, where the indentation is the cost.
- [`php/guard-clauses-and-flow`](../../php/guard-clauses-and-flow/SKILL.md) — the same discipline on the PHP backend.
