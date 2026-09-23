---
name: commandments-python-flow
description: "Shaping a Python function body — a precondition or `None` check at the start of a `def`, an `if` that decides whether the rest of the function runs, an `if`/`elif`/`else` chain, a loop whose whole body sits under one `if`, a block nested three deep, or an `else:` after a branch that already returned or raised. Read this BEFORE writing any of them, and when a Python flow finding points here."
---

# Python flow — guard at the top, keep the body flat

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat
> and at the function's own indentation. Python makes the cost of the other shape visible —
> every condition you wrap the work in is four more spaces the reader has to carry — so a
> function should read top to bottom: *here's what would stop us → here's the work.*

## The principle

### Guard at the top, then leave

Every precondition a function depends on — an argument that must be there, a state that must
hold — is checked **first**, and short-circuits with a `return` or a `raise`. By the time
control reaches the real work, everything it needs is guaranteed, so the work runs at the
function's own indentation with no `else` and no nesting. The shape documents the contract.

```python
def ship(order):
    if order is None:
        raise OrderMissing()
    if not order.lines:
        return None

    carrier = pick_carrier(order)
    return carrier.book(order)
```

### No `else` after a branch that already left

When the `if` returns, raises, `continue`s or `break`s, the code after it only runs when the
condition was false — the `else:` says nothing the exit did not already say, and it pushes the
rest of the function four spaces right. Drop the `else` and dedent.

### A loop body wrapped in one `if` is a `continue` guard

`for line in lines:` followed by an `if` holding the entire body is the same shape inside a
loop: flip the condition, `continue`, and let the body sit at the loop's level.

### An `elif` ladder over one subject is a dispatch

`if status == "paid": … elif status == "late": … elif status == "void": …` tests one value
against case after case. That is a closed set — make it an `Enum` and put the per-case answer on
it, or look the answer up in a dict keyed by the value (`match` works too for a real structural
dispatch). The ladder re-tests the subject on every rung and grows a rung per case forever.

### Depth is the symptom

Every `if`, loop and `match` is one more choice the reader holds open; an `elif` is a rung of the
same choice, and a `try` or a `with` is a boundary, not a choice. Four choices deep — a loop in a
loop in an `if` in a loop — means a decision is buried inside another decision. Guard the outer
one away (`continue` past what does not apply), let a comprehension or a lookup do the inner
iteration, or extract the inner block into a function named for what it decides.

### What is NOT this sin

- An `if`/`else` where neither branch leaves and both are real work of equal weight — a decision,
  not a guard.
- A `try`/`except` or a `with` that the work genuinely runs inside; that nesting is the resource
  or the failure boundary, not a buried condition.
- A comprehension's `if` clause — the filter belongs there.

## Rules

- [ ] Flatten with guard clauses and extraction — never bury a choice four deep inside a function.
      _Guard the outer levels away (`return`/`continue` past what does not apply), let a comprehension do the inner iteration, or extract the inner block into a function named for what it decides._
- [ ] Drop the `else:` after a branch that returns, raises, continues or breaks — let the rest run at the function's own level.
      _Delete the `else:` line and dedent its block; the exit above it already says the rest only runs when the condition was false._

## Worked example

### deep-python-nesting

An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops

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

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/flow` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `deep-python-nesting`, `redundant-python-else`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/guard-clauses-and-flow`](../../backend/guard-clauses-and-flow/SKILL.md) — the same discipline on the PHP backend.
- [`backend/enums-with-behaviour`](../../backend/enums-with-behaviour/SKILL.md) — where an `elif` ladder over one subject goes: a closed set with the per-case answer on it.
