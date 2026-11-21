---
name: commandments-shared-flow
description: "Shaping a function body in any language: an `if` that decides whether the rest runs, an `else` after a branch that already returned, a loop whose whole body sits under one `if`, an if/else-if chain over one value, a ternary inside a ternary, a ternary or `&&` used as a statement, a statement every branch repeats, code after a `return`, or a parameter given a new value. Read this BEFORE writing any of them, and when a flow finding points here."
---

# Flow — guard at the top, keep the body flat

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Decide what would stop you first, at the top, and leave. What is left is the work, flat and at the
> function's own level. A reader then reads a function top to bottom: here is what would stop us, here
> is the work. These rules hold the same in every language, so every language is judged by them the
> same way.

## The principle

### Guard at the top, then leave

Every precondition a function depends on is checked first and leaves: `return`, `throw`, `break`,
`continue`. By the time the work runs, everything it needs is there, so it sits at the function's own
level with no `else` and no nesting.

### No `else` after a branch that already left

When the `if` returns, throws, breaks or continues, what follows it only runs when the condition was
false. The `else` says nothing the exit did not already say, and it indents the rest for nothing. Drop
it and dedent.

### A loop body wrapped in one `if` is a `continue` guard

A loop whose whole body is one `if` is the same shape inside a loop. Flip the condition, `continue`,
and let the body sit at the loop's own level.

### A ladder over one subject is a dispatch

`if (kind == "paid") … else if (kind == "late") … else if …` tests one value case after case. Make
the closed set a type that answers per case, key a lookup by the value, or `switch`/`match` on it.

### One decision per conditional expression

A ternary (`a ? b : c`, `b if a else c`) chooses one value. A ternary inside another folds several
decisions into one line; unfold it into guards that return, a `match`/`switch`, or a lookup.

### Choose an action with an `if`

A ternary whose value nothing reads, or a bare `a && b()`, runs work off an operator. It is an `if`
in disguise: write the `if`.

### Write once what every branch ends with

A statement every branch of an `if`/`else` ends with runs whichever way the condition goes. It belongs
after the conditional, written once.

### Nothing after the block has left

A statement after a `return`, `throw`, `break` or `continue` in the same block can never run. Delete
it, or move it above the exit if it was meant to run.

### A parameter keeps the value it was given

A parameter names what the caller passed. Overwriting it makes one name mean two values, and a
reader below the assignment has to look up which one it holds now. Give the new value a local of
its own, named for what it is, or ask the caller for the finished value.

## Rules

- [ ] Flatten with guards that leave early, or extract the inner decision into a function of its own.
      _Turn the outer conditions into guards that return or `continue`, or move the innermost block into a well-named function._
- [ ] A statement every branch ends with belongs after the conditional, written once.
      _Move the shared last statement below the `if`/`else`._
- [ ] Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`.
      _Make the negated condition the loop's first line, followed by `continue`, and dedent the body under it._
- [ ] Use one conditional expression for one choice; for more, use a `match`/`switch`, a lookup, or guards that return early.
      _A `match`/`switch` over the subject, a lookup for a table of values, or a small function whose guards return early._
- [ ] Keep `for` for a counted loop, whose step advances a counter; walk with a `while`, or let the type hand out its own sequence.
      _A `while` over an explicit cursor, or better, an iterator on the type being walked, so the caller never holds the cursor at all._
- [ ] Load the rows before iterating over them.
      _Fetch the required rows in one query before the loop._
- [ ] A parameter keeps the value its caller passed; a new value gets a name of its own.
      _Assign the new value to a local named for what it is (`trimmed = trim(name)`), or ask the caller for the finished value._
- [ ] Drop the `else` after a branch that returns, throws, continues or breaks; let the rest run at the function's own level.
      _Delete the `else` and dedent what was inside it; the exit above already says the rest only runs when the condition did not hold._
- [ ] Branch with an `if`; never run work off the right side of a bare `&&`/`||` (`and`/`or`) whose value nothing reads.
      _Write the condition as an `if` and the right side as its body, negating it for an `||`._
- [ ] Dispatch on a value with a type that answers per case, a lookup keyed by the value, or a `switch`/`match` on it; never a ladder of equality tests on one subject.
      _Make the closed set an enum and put the per-case answer on it, or key a lookup by the value; a `switch`/`match` fits a structural dispatch._
- [ ] Choose an action with `if`/`else`; a conditional expression chooses a value, so never write one whose value nothing reads.
      _An `if` with each side as its own body, and no `else` at all when one side did nothing._
- [ ] Give a compound condition a name that says what it decides.
      _Put the condition on the type that owns the decision, or give a template condition a named computed value._
- [ ] Every statement in a block can run; code after the block has left is dead.
      _Delete the statement, or move it before the statement that leaves the block if it was meant to run._

## Worked example

### deep-nesting — in PHP

A decision sits deep inside other decisions in one function

```php
----------[ Bad ]----------

public function resolve(Product $product, array $overrides, string $region): int
{
    if (array_key_exists($region, $overrides)) {
        if ($product->price_cents > 0) {
            if ($overrides[$region] < $product->price_cents) {
                return $overrides[$region];
            }
        }
    }

    return $product->price_cents;
}

----------[ Good ]----------

public function resolveFlat(Product $product, array $overrides, string $region): int
{
    if (! array_key_exists($region, $overrides)) {
        return $product->price_cents;
    }

    if ($product->price_cents <= 0) {
        return $product->price_cents;
    }

    return min($overrides[$region], $product->price_cents);
}
```

### deep-nesting — in TypeScript

A decision sits deep inside other decisions in one function

```ts
----------[ Bad ]----------

export function sortIntoBins(parcels: Parcel[], bins: Map<number, Parcel[]>): void {
    for (const parcel of parcels) {
        if (parcel.grams > 0) {
            if (bins.has(parcel.zone)) {
                if (parcel.labels.length > 0) {
                    bins.get(parcel.zone)?.push(parcel)
                }
            }
        }
    }
}

----------[ Good ]----------

export function sortLabelledIntoBins(parcels: Parcel[], bins: Map<number, Parcel[]>): void {
    for (const parcel of parcels) {
        if (parcel.grams <= 0 || parcel.labels.length === 0) {
            continue
        }
        bins.get(parcel.zone)?.push(parcel)
    }
}
```

### deep-nesting — in Python

A decision sits deep inside other decisions in one function

```py
----------[ Bad ]----------

def usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer == customer.id:
            if coupon.starts <= today <= coupon.ends:
                if coupon.uses_left > 0:
                    if not coupon.revoked:
                        return coupon
    return None

----------[ Good ]----------

def first_usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer != customer.id or coupon.revoked:
            continue
        if coupon.starts <= today <= coupon.ends and coupon.uses_left > 0:
            return coupon
    return None
```

### deep-nesting — in C#

A decision sits deep inside other decisions in one function

```cs
----------[ Bad ]----------

// Restocking walks every warehouse's shelves and decides, four choices deep, what to reorder — the
// decision that matters is buried under the ones that only filter.
public sealed class Restock(IReadOnlyDictionary<string, IReadOnlyList<Shelf>> warehouses)
{
    public IReadOnlyList<string> Reorders()
    {
        var reorders = new List<string>();

        foreach (var (warehouse, shelves) in warehouses)
        {
            foreach (var shelf in shelves)
            {
                if (shelf.OnHand < shelf.Minimum)
                {
                    if (shelf.Supplier is not null)
                    {
                        reorders.Add($"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}");
                    }
                }
            }
        }

        return reorders;
    }

    public IReadOnlyList<string> ReordersFlat() =>
        warehouses
            .SelectMany(warehouse => warehouse.Value.Select(shelf => (Warehouse: warehouse.Key, Shelf: shelf)))
            .Where(entry => entry.Shelf.OnHand < entry.Shelf.Minimum && entry.Shelf.Supplier is not null)
            .Select(entry => Reorder(entry.Warehouse, entry.Shelf))
            .ToList();

    private static string Reorder(string warehouse, Shelf shelf) =>
        $"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}";
}

----------[ Good ]----------

// Restocking walks every warehouse's shelves and decides, four choices deep, what to reorder — the
// decision that matters is buried under the ones that only filter.
public sealed class Restock(IReadOnlyDictionary<string, IReadOnlyList<Shelf>> warehouses)
{
    public IReadOnlyList<string> Reorders()
    {
        var reorders = new List<string>();

        foreach (var (warehouse, shelves) in warehouses)
        {
            foreach (var shelf in shelves)
            {
                if (shelf.OnHand < shelf.Minimum)
                {
                    if (shelf.Supplier is not null)
                    {
                        reorders.Add($"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}");
                    }
                }
            }
        }

        return reorders;
    }

    public IReadOnlyList<string> ReordersFlat() =>
        warehouses
            .SelectMany(warehouse => warehouse.Value.Select(shelf => (Warehouse: warehouse.Key, Shelf: shelf)))
            .Where(entry => entry.Shelf.OnHand < entry.Shelf.Minimum && entry.Shelf.Supplier is not null)
            .Select(entry => Reorder(entry.Warehouse, entry.Shelf))
            .ToList();

    private static string Reorder(string warehouse, Shelf shelf) =>
        $"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}";
}
```

### deep-nesting — in Go

A decision sits deep inside other decisions in one function

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

The other 50 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/flow` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `deep-nesting`, `duplicated-branch-tail`, `loop-inverted-guard`, `nested-ternary`, `non-counting-for`, `query-in-loop`, `reassigned-parameter`, `redundant-else`, `short-circuit-statement`, `subject-ladder`, `ternary-statement`, `unnamed-condition`, `unreachable-statement`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 55 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/guard-clauses-and-flow`](../../php/guard-clauses-and-flow/SKILL.md) — what is particular to PHP's flow.
- [`python/flow`](../../python/flow/SKILL.md) — what is particular to Python's flow, where the indentation is the cost.
- [`typescript/flow`](../../typescript/flow/SKILL.md) — what is particular to TypeScript's flow.
- [`csharp/flow`](../../csharp/flow/SKILL.md) — what is particular to C#'s flow.
- [`go/flow`](../../go/flow/SKILL.md) — what is particular to Go's flow.
