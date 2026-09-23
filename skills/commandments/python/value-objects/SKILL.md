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

## Rules

- [ ] Give values that always travel together one type, and pass that instead of the loose values.
      _Declare a frozen dataclass with those fields and take it as one parameter wherever the loose values travelled together._
- [ ] Give a record a type — a frozen dataclass — instead of a dict read by string keys.
      _Declare the keys as fields of a frozen dataclass, build it where the data enters (a `from_payload` classmethod), and take that type as the parameter._

## Worked example

### python-data-clump

The same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept wearing no name

```py
----------[ Bad ]----------

# in delivery.py
def book_delivery(street: str, city: str, postcode: str, carrier) -> str:
    return carrier.book(f"{street}, {postcode} {city}")

# in quotes.py
def quote(postcode: str, street: str, city: str, weight_kg) -> int:
    return 495 if postcode.startswith("1") else 695

# in customers.py
def subscribe(self, name: str, email: str, phone: str, opt_in: bool) -> None:
    self.list.add(email, name)

# in customers.py
def enrol(self, phone: str, name: str, email: str, opt_in: bool = False) -> None:
    self.members.add(name, email, phone, opt_in)

----------[ Good ]----------

# in delivery.py
@dataclass(frozen=True)
class Address:
    street: str
    city: str
    postcode: str

    def line(self) -> str:
        return f"{self.street}, {self.postcode} {self.city}"

# in delivery.py
def schedule_delivery(address: Address, carrier) -> str:
    return carrier.book(address.line())
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/value-objects` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-data-clump`, `python-dict-bag`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/value-objects`](../../backend/value-objects/SKILL.md) — the same discipline on the PHP backend.
- [`python/absence`](../absence/SKILL.md) — a field that is always there is typed as such, not read with `.get(...) or ""`.
