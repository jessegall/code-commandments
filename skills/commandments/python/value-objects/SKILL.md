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

- [ ] Fields that move as a unit are one type: hold the value object, not its parts; never keep a second copy of what a sibling field already holds.
      _Fold the fields into one frozen dataclass (name the existing one when the clump already is it) and drop a field that mirrors a sibling's attribute._
- [ ] Give values that always travel together one type, and pass that instead of the loose values.
      _Declare a frozen dataclass with those fields and take it as one parameter wherever the loose values travelled together._
- [ ] Give a record a type — a frozen dataclass — instead of a dict read by string keys.
      _Declare the keys as fields of a frozen dataclass, build it where the data enters (a `from_payload` classmethod), and take that type as the parameter._
- [ ] Return a typed value — a frozen dataclass — not a dict of several named fields.
      _A `@dataclass(frozen=True)` for the result, built where it is returned — or a `TypedDict` when a dict must cross a boundary._
- [ ] Derive a changed dataclass with `dataclasses.replace`, naming only what changes; don't re-list every field by hand.
      _`return replace(self, status="paid")` — a field added later needs no change here._
- [ ] Make a value immutable: build it complete and derive a new one to change it; never write its fields after construction.
      _`@dataclass(frozen=True)` and `return replace(self, amount=…)` from the method that changed it._
- [ ] Return a named result — a frozen dataclass or a NamedTuple — not a tuple of different things the caller unpacks by position.
      _A small `@dataclass(frozen=True)` (or `NamedTuple`) whose fields name each slot._
- [ ] Parse decoded data into a typed value at the boundary; never hand back a raw `json.loads(...)` result.
      _Build the dataclass (or a TypedDict-typed value) from the decoded data where it arrives — `Settings.from_json(json.loads(text))`._

## Worked example

### python-coupled-fields

a class whose own fields always travel together — assembled into one value again and again, guarded together, or one mirroring a sibling's — one concept held as several fields

```py
----------[ Bad ]----------

class Weekday:
    def __init__(self, name: str, opens: int, closes: int) -> None:
        self.name = name
        self.opens = opens
        self.closes = closes

    def hours(self) -> Hours:
        return Hours(self.opens, self.closes)

    def label(self) -> str:
        opens, closes = (self.opens, self.closes)
        return f"{self.name}: {opens}-{closes}"

----------[ Good ]----------

class OpenWeekday:
    def __init__(self, name: str, hours: Hours) -> None:
        self.name = name
        self.hours = hours

    def label(self) -> str:
        return f"{self.name}: {self.hours.opens}-{self.hours.closes}"
```

The other 7 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/value-objects` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-coupled-fields`, `python-data-clump`, `python-dict-bag`, `python-dict-return-bag`, `python-hand-rolled-replace`, `python-mutable-value-object`, `python-positional-tuple-return`, `python-raw-decoded-return`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 8 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/value-objects`](../../backend/value-objects/SKILL.md) — the same discipline on the PHP backend.
- [`python/absence`](../absence/SKILL.md) — a field that is always there is typed as such, not read with `.get(...) or ""`.
