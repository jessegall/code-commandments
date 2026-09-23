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

## Related skills

- [`backend/tell-dont-ask`](../../backend/tell-dont-ask/SKILL.md) — the same discipline over PHP.
- [`python/enums`](../enums/SKILL.md) — a closed set of cases carries its per-case behaviour on the `Enum`.
- [`python/value-objects`](../value-objects/SKILL.md) — the type the behaviour moves onto.
