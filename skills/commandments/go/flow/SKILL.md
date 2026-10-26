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
> 

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

### Depth is the symptom

Every `if`, `for`, `switch` and `select` is one more choice the reader holds open. Four deep means a
decision is buried inside another decision: guard the outer one away, let the inner iteration be a
function of its own, or name the inner block for what it decides.

## Rules

- [ ] Flatten with guards and extraction — never bury a choice four deep inside a function.
      _Guard the outer levels away (`return`, `break` or `continue` past what does not apply), or give the inner block a function of its own named for what it decides._
- [ ] Invert a loop body wrapped in one `if` into a `continue` guard so the work sits at the loop's own level.
      _Write `if !<condition> { continue }` as the loop's first statement and dedent the body under it._
- [ ] Drop the `else` after a branch that returns, breaks, continues, goes to or panics — let the rest run at the function's own level.
      _Delete the `else {` and its closing brace and dedent what was inside; the exit above it already says the rest only runs when the condition did not hold._

## Worked example

### deep-go-nesting

An `if`, `for`, `switch` or `select` opening a fourth level of choices inside one Go function — a decision buried inside a decision

```go
----------[ Bad ]----------

// Dispatch sends out every crate the bay is holding.
func (b *Bay) Dispatch(bays []*Bay) int {
	sent := 0
	for _, bay := range bays {
		if !bay.closed {
			for _, crate := range bay.crates {
				if crate.Packed {
					sent++
				}
			}
		}
	}

	return sent
}

----------[ Good ]----------

// Sent is what Dispatch counts, with each level guarded away instead of nested.
func (b *Bay) Sent(bays []*Bay) int {
	sent := 0
	for _, bay := range bays {
		if bay.closed {
			continue
		}

		sent += bay.packed()
	}

	return sent
}
```

The other 2 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=go/flow` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `deep-go-nesting`, `go-loop-wrapped-in-if`, `redundant-go-else`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 3 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`python/flow`](../../python/flow/SKILL.md) — the same discipline in Python, where the indentation is the cost.
- [`php/guard-clauses-and-flow`](../../php/guard-clauses-and-flow/SKILL.md) — the same discipline on the PHP backend.
