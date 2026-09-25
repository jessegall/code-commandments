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