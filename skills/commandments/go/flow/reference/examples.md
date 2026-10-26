# Go flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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

### go-loop-wrapped-in-if

A `for` whose whole body is one `if`, burying the work a level deep where `continue` past what does not apply would keep it flat

```go
----------[ Bad ]----------

// Post adds every entry that stands for money to the day's totals.
func (l *Ledger) Post(entries []Entry) {
	for _, entry := range entries {
		if !entry.Void && entry.Cents != 0 {
			l.totals[entry.Account] += entry.Cents
			l.posted++
		}
	}
}

----------[ Good ]----------

// PostFlat posts the same entries with the work at the loop's own level: the loop says what it skips in its
// first line, and everything after it is the posting.
func (l *Ledger) PostFlat(entries []Entry) {
	for _, entry := range entries {
		if entry.Void || entry.Cents == 0 {
			continue
		}
		l.totals[entry.Account] += entry.Cents
		l.posted++
	}
}
```

### redundant-go-else

An `else` after an `if` branch that already left — it ends in `return`, `break`, `continue`, `goto` or `panic` — indenting the rest of the function for nothing

```go
----------[ Bad ]----------

// Available is what may still be sold of an item.
func (w *Warehouse) Available(sku string) int {
	if w.closed {
		return 0
	} else {
		return w.onHand[sku] - w.promised[sku]
	}
}

----------[ Good ]----------

// AvailableTold is the same answer with the else gone: the return above it already says the rest runs only
// when the warehouse is open, so what was inside stands at the method's own level.
func (w *Warehouse) AvailableTold(sku string) int {
	if w.closed {
		return 0
	}

	return w.onHand[sku] - w.promised[sku]
}
```
