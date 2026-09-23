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

Three blocks deep means a decision is buried inside another decision. Guard the outer one away,
or extract the inner block into a function named for what it decides.

### What is NOT this sin

- An `if`/`else` where both branches are real work of equal weight — a decision, not a guard.
- A `try`/`except` or a `with` that the work genuinely runs inside; that nesting is the resource
  or the failure boundary, not a buried condition.
- A comprehension's `if` clause — the filter belongs there.

## Related skills

- [`backend/guard-clauses-and-flow`](../../backend/guard-clauses-and-flow/SKILL.md) — the same discipline on the PHP backend.
- [`backend/enums-with-behaviour`](../../backend/enums-with-behaviour/SKILL.md) — where an `elif` ladder over one subject goes: a closed set with the per-case answer on it.
