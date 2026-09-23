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

## Related skills

- [`backend/enums-with-behaviour`](../../backend/enums-with-behaviour/SKILL.md) — the same discipline on the PHP backend.
- [`python/flow`](../flow/SKILL.md) — an `elif` ladder over one subject is where a missing enum usually shows first.
