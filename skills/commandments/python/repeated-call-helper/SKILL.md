---
name: commandments-python-repeated-call-helper
description: "When you write the same thing the same way at site after site in Python — the same keyword call (`replace(order, status=...)`, `model.copy(update={...})`), the same compound `if` condition, the same `isinstance(x, A) and isinstance(x.y, B)` narrowing. Read this before copying a condition or a keyword call from one function into another, and when a repeated-guard or repeated-call finding points here."
---

# Python repeated call helper — name what you keep spelling out

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Something you write the same way over and over is a function you have not named yet. When the same
> keyword call, the same condition or the same type check recurs, give it a name — usually as a method on the
> type it is about — and let every site say what it means instead of how it is worked out.

## The principle

### The repeated keyword call

`dataclasses.replace(order, status="shipped")`, `settings.model_copy(update={"debug": True})`,
`point._replace(x=0)` — a generic "copy with changes" API is flexible, and at ONE site that flexibility is
the point. Written the same way at site after site, it is an operation the type should name:

```python
@dataclass(frozen=True)
class Order:
    status: str

    def shipped(self) -> "Order":
        return replace(self, status="shipped")
```

Now every site says `order.shipped()`. The generic `replace` stays for the genuinely one-off change.

### The repeated guard

The same compound condition — `if order.paid and not order.cancelled and order.lines:` — written in two
places is a question with no name. Spelled differently (a local here, an inline attribute there) or in
another order, it is still the same question. Name it where the data lives:

```python
@property
def is_shippable(self) -> bool:
    return self.paid and not self.cancelled and bool(self.lines)
```

and every site asks `if order.is_shippable:`. When the rule changes, it changes once.

### The repeated type guard

`isinstance(node, Call) and isinstance(node.func, Attribute)` copied from function to function is a check
on a shape that has no name. Give it one — a method, a property, or a `TypeGuard` function — so the shape is
declared once and every site asks for it by name.

### When it is NOT this sin

- **A one-off.** A call or a condition written once is exactly what the general tool is for.
- **Genuinely different arguments.** `replace(order, status=…)` here and `replace(order, note=…)` there are
  two operations, not one.
- **A simple check.** A single `if x is None:` or one `isinstance` is not a compound condition; repeating
  it names nothing new.

## Rules

- [ ] Name a compound condition you write twice — a property or method on the type it asks about — and ask it by name at every site.
      _Move the condition onto the type as `is_…` / `can_…` and replace every copy with the call._
- [ ] Name a keyword call you keep writing the same way: a method on the type — `node.with_meta(payload)` — that hides the call and the construction.
      _Add a method to the receiver's class that makes the call, and call that at every site._
- [ ] Name a type narrowing you write twice — a method, a property or a `TypeGuard` function — and ask for the shape by name.
      _Move the chain into one named predicate (a `TypeGuard` where the caller needs the narrowed type) and call it at every site._

## Worked example

### python-repeated-guard

the same compound `and` condition recurs in 2+ places — it still counts even when reordered, or read through a local variable — and nobody has named it.

```py
----------[ Bad ]----------

# in loyalty_points.py
def redeem(member: Member, cost: int) -> int:
    if member.points >= cost and not member.frozen:
        return member.points - cost
    raise ValueError(cost)

# in loyalty_points.py
def preview(member: Member, cost: int) -> str:
    enough = member.points >= cost
    return "redeemable" if not member.frozen and enough else "locked"

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in dispatch_desk.py
def outgoing(self) -> list[Parcel]:
    return [parcel for parcel in self.parcels if parcel.labelled and parcel.weight_grams > 0]

# in dispatch_desk.py
def may_leave(self, parcel: Parcel) -> bool:
    return parcel.labelled and parcel.weight_grams > 0

# in courier_pickups.py
def load(van: list[Parcel], parcel: Parcel) -> None:
    if parcel.labelled and parcel.weight_grams > 0:
        van.append(parcel)

----------[ Good ]----------

class Account:
    def __init__(self, points: int, frozen: bool) -> None:
        self.points = points
        self.frozen = frozen

    def can_redeem(self, cost: int) -> bool:
        return self.points >= cost and not self.frozen

    def redeem(self, cost: int) -> int:
        if self.can_redeem(cost):
            return self.points - cost
        raise ValueError(cost)
```

The other 2 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/repeated-call-helper` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-repeated-guard`, `python-repeated-named-call`, `python-repeated-type-guard`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 3 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/repeated-call-helper`](../../backend/repeated-call-helper/SKILL.md) — the same discipline over PHP.
- [`python/duplication`](../duplication/SKILL.md) — a whole function body written twice, rather than one call or condition.
- [`python/fix-at-the-source`](../fix-at-the-source/SKILL.md) — name the question where the data lives, not beside each caller.
