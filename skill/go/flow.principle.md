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
