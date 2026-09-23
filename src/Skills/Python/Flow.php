<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Flow extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/flow',
            tier: Tier::Mandatory,
            order: 29,
        );
    }

    public function title(): string
    {
        return 'Python flow — guard at the top, keep the body flat';
    }

    public function trigger(): string
    {
        return "Shaping a Python function body — a precondition or `None` check at the start of a `def`, an `if` that decides whether the rest of the function runs, an `if`/`elif`/`else` chain, a loop whose whole body sits under one `if`, a block nested three deep, or an `else:` after a branch that already returned or raised. Read this BEFORE writing any of them, and when a Python flow finding points here.";
    }

    public function intro(): string
    {
        return "Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat
and at the function's own indentation. Python makes the cost of the other shape visible —
every condition you wrap the work in is four more spaces the reader has to carry — so a
function should read top to bottom: *here's what would stop us → here's the work.*";
    }

    public function summary(): string
    {
        return 'check preconditions at the top and leave (`return`/`raise`/`continue`), keep the body flat, no `else` after an exit, dispatch instead of an `elif` ladder over one subject.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
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
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow::class => 'the same discipline on the PHP backend.',
            \JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour::class => 'where an `elif` ladder over one subject goes: a closed set with the per-case answer on it.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
