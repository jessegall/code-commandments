---
name: commandments-python-tell-dont-ask
description: "Behaviour belongs with the data it works on. Read this BEFORE you write a Python function that loops over another object's collection or walks its parts to work out something that object could answer, and before an `if isinstance(x, A): … elif isinstance(x, B): …` ladder that asks what a value IS to decide what to do with it — the answer is a method on the type (`order.total()`, `shape.area()`)."
---

# Python tell, don't ask — behaviour lives with its data

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Don't ask an object for its insides and then decide for it; tell it what you want and let it answer. A
> function that reaches into one object's collection, or asks what type a value is before acting, is behaviour
> exiled from the class that owns the data.

## The principle

### Feature envy

```python
def total(order: Order) -> int:
    return sum(line.price * line.quantity for line in order.lines)
```

This function knows how an order is built — it has lines, a line has a price and a quantity — and works it
out from outside. Every caller that needs the total either imports this helper or writes the loop again. The
knowledge belongs on the order:

```python
class Order:
    def total(self) -> int:
        return sum(line.subtotal() for line in self.lines)
```

Now `order.total()` is asked, not computed at the caller, and a change to how an order is built changes one
class.

### The type switch

```python
if isinstance(shape, Circle):
    area = pi * shape.radius ** 2
elif isinstance(shape, Square):
    area = shape.side ** 2
```

asks each value what it IS so the caller can decide what to do. Every new shape means finding every ladder.
Tell instead: give each type the method — `shape.area()` — and let the value answer for itself. Where the
types are not yours to change, `functools.singledispatch` states the per-type behaviour in one registry
instead of a ladder at every call site.

### What is NOT this sin

- **A policy over flat fields.** A pricing Strategy that reads a customer's tier and a basket's weight is a
  rule ABOUT the data, not the data's own behaviour; keeping it apart is a design choice.
- **Reading one attribute.** `order.id` read to log it is not envy; envy is working out what the object
  could answer.
- **Parsing at the edge.** An `isinstance` check that turns loose input (`dict`, `list`, `str`) into your
  types is where the types are born, not a switch over them.

## Rules

- [ ] Move the behaviour onto the object whose data it works on; ask it (`order.heaviest_line()`), don't reach through it.
      _Move the method onto the envied class and call it there; keep only the orchestration here._
- [ ] Put the fact on the object it is about and ask it (`node.reserved_names()`), instead of looking it up from outside by the object's key.
      _Give the object the method, holding what it needs to answer, and call it where this method was called._
- [ ] Give each type the method and call it (`shape.area()`) instead of asking a value what it is in an `isinstance` ladder.
      _Declare the method on the shared base, implement it on each class, and replace the ladder with the call._

## Worked example

### python-feature-envy

a method that loops another object's collection or writes its fields, reaching into it more than into its own state — behaviour exiled from the object it works on

```py
----------[ Bad ]----------

def redeem(self, card: StampCard) -> None:
    card.closed = True
    card.reward = "free coffee"
    card.stamps = 0
    self.rewards_given += 1

----------[ Good ]----------

# in stamp_cards.py
def close_with(self, reward: str) -> None:
    self.closed = True
    self.reward = reward
    self.stamps = 0

# in stamp_cards.py
def redeem_card(self, card: StampCard) -> None:
    card.close_with("free coffee")
    self.rewards_given += 1
```

The other 2 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/tell-dont-ask` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-feature-envy`, `python-keyed-lookup-envy`, `python-type-switch`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 3 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/tell-dont-ask`](../../backend/tell-dont-ask/SKILL.md) — the same discipline over PHP.
- [`python/enums`](../enums/SKILL.md) — a closed set of cases carries its per-case behaviour on the `Enum`.
- [`python/value-objects`](../value-objects/SKILL.md) — the type the behaviour moves onto.
