---
name: commandments-python-pass-the-object
description: "Writing a Python function that takes an object and an id and whose first move is to look one up in the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — or one that takes a value its caller had to convert, derive or compute a bool from first. Read this before adding an `_id` parameter beside the object it keys into, and when a pass-the-object finding points here."
---

# Python pass the object — demand what you use, not an id and its container

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> When a function's first act is to resolve one parameter against another, the signature lies about what the
> function needs. The caller resolves once, where the id was born, and passes the object the function works on.

## The principle

```python
def rename(workflow: Workflow, node_id: str, title: str) -> None:
    node = workflow.graph.node(node_id)
    node.title = title
```

The function only ever wanted the node. Taking the workflow and an id means:

- **The caller already had both.** It can resolve the node itself; nothing is gained by deferring it.
- **The not-found failure lands in the wrong place.** Whoever named the id is the one who can say what a
  missing node means; buried inside `rename`, that error handling spreads to every function like it.
- **The type says nothing.** `node_id: str` where a `Node` is meant is primitive obsession.
- **It ties the function to the container's lookup** (`workflow.graph.node`) for no reason.

So the signature demands what it uses — `def rename(node: Node, title: str)` — and the caller resolves once.

### The same smell, other shapes

- **A converted argument** — every caller passes `str(order.id)` or `Decimal(amount)`: the function wants
  the other type; take it, or take the object and convert inside, once.
- **A derived argument** — every caller passes `order.customer` beside `order`: the function can read it.
- **A computed bool** — every caller passes `is_paid=order.status == "paid"`: hand over the order and let
  the function ask it.

### What is NOT this sin

- **A registry or repository keyed into its own store** — `self._handlers[kind]` is the object's job.
- **A boundary** — a view, a CLI command or a task handler receives ids from outside; that is where they are
  resolved.
- **A lookup whose contract is "by id"** — `find_by_id(id)` that hands the result straight back.

## Rules

- [ ] Hand the method the object its callers keep asking, and let it ask; a bool every caller computes the same way is a decision living in the wrong place.
      _Take the object (`text(order)`) and read `order.status`/`order.total` inside, so the rule lives once._
- [ ] Declare the parameter in the type callers actually hold and convert inside — one rule about the conversion, in one place.
      _Move the conversion into the function and take what the callers had (`receipt_for(order)` or `receipt_for(order_id: int)`); a caller that forgets the conversion can no longer pass the wrong thing._

## Worked example

### python-computed-boolean-argument

a method taking only bools that every caller computes from the same object — the decision re-derived at each call site

```py
----------[ Bad ]----------

def allows(self, expired: bool) -> bool:
    return not expired

----------[ Good ]----------

# in return_desk.py
def allows_for(self, purchase: Purchase) -> bool:
    return purchase.days_since <= 30

# in return_desk.py
def offers_honestly(self, purchases: list[Purchase]) -> list[Purchase]:
    return [purchase for purchase in purchases if self.policy.allows_for(purchase)]
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/pass-the-object` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-computed-boolean-argument`, `python-converted-argument`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/pass-the-object`](../../backend/pass-the-object/SKILL.md) — the same discipline over PHP methods.
- [`python/tell-dont-ask`](../tell-dont-ask/SKILL.md) — the sibling: once you hold the object, ask it rather than reaching into it.
- [`python/value-objects`](../value-objects/SKILL.md) — the type an id stands in for.
