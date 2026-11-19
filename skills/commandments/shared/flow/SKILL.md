---
name: commandments-shared-flow
description: "Shaping a function body in any language: an `if` that decides whether the rest runs, an `else` after a branch that already returned, a loop whose whole body sits under one `if`, an if/else-if chain over one value, a ternary inside a ternary, a ternary or `&&` used as a statement, a statement every branch repeats, or code after a `return`. Read this BEFORE writing any of them, and when a flow finding points here."
---

# Flow — guard at the top, keep the body flat

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Decide what would stop you first, at the top, and leave. What is left is the work, flat and at the
> function's own level. A reader then reads a function top to bottom: here is what would stop us, here
> is the work. These rules hold the same in every language, so every language is judged by them the
> same way.
> 

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

## Rules

- [ ] A statement every branch ends with belongs after the conditional, written once.
      _Move the shared last statement below the `if`/`else`._
- [ ] Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`.
      _Make the negated condition the loop's first line, followed by `continue`, and dedent the body under it._
- [ ] Use one conditional expression for one choice; for more, use a `match`/`switch`, a lookup, or guards that return early.
      _A `match`/`switch` over the subject, a lookup for a table of values, or a small function whose guards return early._
- [ ] Drop the `else` after a branch that returns, throws, continues or breaks; let the rest run at the function's own level.
      _Delete the `else` and dedent what was inside it; the exit above already says the rest only runs when the condition did not hold._
- [ ] Branch with an `if`; never run work off the right side of a bare `&&`/`||` (`and`/`or`) whose value nothing reads.
      _Write the condition as an `if` and the right side as its body, negating it for an `||`._
- [ ] Dispatch on a value with a type that answers per case, a lookup keyed by the value, or a `switch`/`match` on it; never a ladder of equality tests on one subject.
      _Make the closed set an enum and put the per-case answer on it, or key a lookup by the value; a `switch`/`match` fits a structural dispatch._
- [ ] Choose an action with `if`/`else`; a conditional expression chooses a value, so never write one whose value nothing reads.
      _An `if` with each side as its own body, and no `else` at all when one side did nothing._
- [ ] Every statement in a block can run; code after the block has left is dead.
      _Delete the statement, or move it before the statement that leaves the block if it was meant to run._

## Worked example

### duplicated-branch-tail — in PHP

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```php
----------[ Bad ]----------

public function refund(int $orderId, int $cents): void
{
    if ($cents <= 5000) {
        $this->log[] = "card {$cents}";
        $this->note($orderId);
    } else {
        $this->log[] = "credit {$cents}";
        $this->note($orderId);
    }
}

----------[ Good ]----------

public function refundOnce(int $orderId, int $cents): void
{
    $this->log[] = ($cents <= 5000 ? 'card ' : 'credit ') . $cents;
    $this->note($orderId);
}
```

### duplicated-branch-tail — in TypeScript

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```ts
----------[ Bad ]----------

export function label(parcel: Parcel): Parcel {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
        parcel.zone = Math.max(parcel.zone, 1)
    } else if (parcel.grams > 500) {
        parcel.labels.push('medium')
        parcel.zone = Math.max(parcel.zone, 1)
    } else {
        parcel.zone = Math.max(parcel.zone, 1)
    }

    return parcel
}

----------[ Good ]----------

export function labelOnce(parcel: Parcel): Parcel {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
    } else if (parcel.grams > 500) {
        parcel.labels.push('medium')
    }
    parcel.zone = Math.max(parcel.zone, 1)

    return parcel
}
```

### duplicated-branch-tail — in Python

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```py
----------[ Bad ]----------

def restock(item: ParcelReturn, shelf: Shelf, log: list[str]) -> None:
    if item.opened:
        shelf.seconds += 1
        log.append(item.reason)
    else:
        shelf.fresh += 1
        log.append(item.reason)

----------[ Good ]----------

def restock_once(item: ParcelReturn, shelf: Shelf, log: list[str]) -> None:
    if item.opened:
        shelf.seconds += 1
    else:
        shelf.fresh += 1
    log.append(item.reason)
```

### duplicated-branch-tail — in C#

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```cs
----------[ Bad ]----------

public void Restock(ReturnedParcel parcel)
{
    if (parcel.Opened)
    {
        Seconds++;
        Log.Add(parcel.Reason);
    }
    else
    {
        Fresh++;
        Log.Add(parcel.Reason);
    }
}

----------[ Good ]----------

public void RestockOnce(ReturnedParcel parcel)
{
    if (parcel.Opened)
    {
        Seconds++;
    }
    else
    {
        Fresh++;
    }
    Log.Add(parcel.Reason);
}
```

### duplicated-branch-tail — in Go

Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.

```go
----------[ Bad ]----------

// Label names a parcel heavy or light on its last label.
func Label(parcel Parcel) Parcel {
	if parcel.Grams > 2000 {
		parcel.Labels = append(parcel.Labels, "heavy")
		parcel.Zone = normalZone(parcel.Zone)
	} else if parcel.Grams > 500 {
		parcel.Labels = append(parcel.Labels, "medium")
		parcel.Zone = normalZone(parcel.Zone)
	} else {
		parcel.Zone = normalZone(parcel.Zone)
	}

	return parcel
}

----------[ Good ]----------

// LabelOnce labels by weight and settles the zone once, after the branches.
func LabelOnce(parcel Parcel) Parcel {
	if parcel.Grams > 2000 {
		parcel.Labels = append(parcel.Labels, "heavy")
	} else if parcel.Grams > 500 {
		parcel.Labels = append(parcel.Labels, "medium")
	}
	parcel.Zone = normalZone(parcel.Zone)

	return parcel
}
```

The other 29 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/flow` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `duplicated-branch-tail`, `loop-inverted-guard`, `nested-ternary`, `redundant-else`, `short-circuit-statement`, `subject-ladder`, `ternary-statement`, `unreachable-statement`.
- `{{binary}} repent --sin=<sin>` — auto-fix, for `loop-inverted-guard`, `nested-ternary`, `redundant-else`, `short-circuit-statement`, `ternary-statement`. Review it with `--dry-run` first.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 34 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/guard-clauses-and-flow`](../../php/guard-clauses-and-flow/SKILL.md) — what is particular to PHP's flow.
- [`python/flow`](../../python/flow/SKILL.md) — what is particular to Python's flow, where the indentation is the cost.
- [`typescript/flow`](../../typescript/flow/SKILL.md) — what is particular to TypeScript's flow.
- [`csharp/flow`](../../csharp/flow/SKILL.md) — what is particular to C#'s flow.
- [`go/flow`](../../go/flow/SKILL.md) — what is particular to Go's flow.
