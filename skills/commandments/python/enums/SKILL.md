---
name: commandments-python-enums
description: "A fixed set of values in Python — statuses, kinds, modes — written as string literals compared at call sites (`if status == \"paid\"`, `kind in (\"box\", \"pallet\")`), as module constants, or dispatched on with a `match` over strings. Read this BEFORE comparing a value against a literal it is one of a handful of."
---

# Python enums — seal the set, put the knowledge on the case

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A closed set of values is a **type**, and what you do per value belongs **on** that type. The
> smell is a set spelled as loose strings, compared again at every place that needs to know
> something about one of them.

## The principle

### Seal the set

A value that can only ever be one of a handful — `"paid"`, `"pending"`, `"refunded"` — is an
`Enum` (or a `StrEnum` where it must still read as its string on the wire):

```python
from enum import StrEnum


class Status(StrEnum):
    PENDING = "pending"
    PAID = "paid"
    REFUNDED = "refunded"

    def is_settled(self) -> bool:
        return self in (Status.PAID, Status.REFUNDED)
```

A typo is now an `AttributeError` where it is written, the type checker sees every case, and
there is one place to read the whole set.

### Put the knowledge on the case

The per-case answers — a label, a colour, "is this final?" — are methods on the enum, written once
with every case in view. A comparison against string literals at a call site is that method,
homeless: the next call site writes it again, a little differently.

### Parse once, at the edge

A string arriving from JSON, a form or a database becomes the enum where it enters —
`Status(payload["status"])` — and fails there if it is not one of the cases. From then on the code
passes the enum, never the string.

## Rules

- [ ] Seal a closed set of values as an `Enum` or `StrEnum`, so the set is a type and its cases have a home for behaviour.
      _`class Status(StrEnum): PENDING = "pending"` — then give the per-case knowledge methods on the enum._
- [ ] Name a group of an enum's members as a method or property on the enum; don't re-list the members in an `or` chain at every call site.
      _A property on the enum — `def is_open(self) -> bool: return self in (Status.PENDING, Status.LATE)` — and `s.is_open` at the call site._
- [ ] Put a mapping over an enum's cases on the enum, matching its members; never match its raw `.value` at a call site.
      _A method on the enum — `def badge(self) -> str: match self: case Status.PAID: …` — and `status.badge()` at the call site._
- [ ] Test membership in an enum's group through the enum; never re-list its values as literals in an `in` test.
      _A property on the enum naming the group — `Status(x).is_open` — or `x in (Status.PENDING, Status.LATE)` when the value is already the enum._

## Worked example

### python-constant-class-enum

a class that is nothing but `PENDING = "pending"` constants — a closed set of values written out by hand instead of an `Enum`

```py
----------[ Bad ]----------

class Carrier(object):
    POSTNL = "PNL"
    DHL = "DHL"

----------[ Good ]----------

class CarrierCode(StrEnum):
    POSTNL = "PNL"
    DHL = "DHL"
```

The other 3 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/enums` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-constant-class-enum`, `python-enum-case-or-chain`, `python-enum-value-match`, `python-in-literals-mirrors-enum`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/enums-with-behaviour`](../../backend/enums-with-behaviour/SKILL.md) — the same discipline on the PHP backend.
- [`python/flow`](../flow/SKILL.md) — an `elif` ladder over one subject is where a missing enum usually shows first.
