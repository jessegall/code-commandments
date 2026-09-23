---
name: commandments-python-value-objects
description: "Passing or returning a `dict` whose string keys are a fixed record (`{\"sku\": …, \"quantity\": …}`), reading `row[\"field\"]` or `payload.get(\"field\")` on data your own code built, or adding a third parameter that always travels with two others. Read this BEFORE you shape data as a dict or grow a signature — the answer is usually a frozen dataclass."
---

# Python value objects — give related data a type

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A dict with string keys is a record nobody declared. Every reader re-learns its shape from
> the code that happened to build it, a typo in a key is a `KeyError` at run time instead of an
> error in the editor, and nothing says which keys are always there.

## The principle

### A record is a type

When the keys of a dict are known in advance — the same handful, read by name — the dict is a record,
and a record is a type:

```python
from dataclasses import dataclass


@dataclass(frozen=True)
class Line:
    sku: str
    quantity: int
    unit_price: int
```

The fields are declared once, the type checker sees every read, a missing field fails where the value
is built, and `frozen=True` means nobody changes it behind your back. Behaviour that reads only those
fields — a total, a label — becomes a method on it.

### Build it at the edge

Loose data arrives as dicts: JSON, a form, a row. Turn it into the type **where it enters** — one
`Line.from_payload(payload)` — and pass the type from there on. The rest of the program never sees
the dict, so it never has to wonder which keys are there.

### Values that always travel together are one value

Three parameters that every caller passes side by side — `street, city, postcode` — are an `Address`
waiting to be named. Give them one type and pass that.

### What is NOT this sin

- A dict used as a **mapping**: keys that are data (a SKU → stock level), iterated or looked up by a
  value you did not write in the source.
- `**kwargs` forwarded unchanged, and the dict a serializer hands you right before you convert it.

## Related skills

- [`backend/value-objects`](../../backend/value-objects/SKILL.md) — the same discipline on the PHP backend.
- [`python/absence`](../absence/SKILL.md) — a field that is always there is typed as such, not read with `.get(...) or ""`.
