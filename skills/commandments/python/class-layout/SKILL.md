---
name: commandments-python-class-layout
description: "Adding a class attribute, a constant, or a dataclass field to a Python class — especially one that already has methods — or placing a nested class, a `ClassVar` or an annotated field. Read this BEFORE you declare state below a `def`, and when a class-layout finding points here."
---

# Python class layout — the inventory at the top

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> The head of a class is its inventory. The first screen should tell you everything the object holds —
> its constants, its fields, what it is configured by — before a single `def` asks for your attention. That
> only works if it is total: one attribute declared under the methods turns "the state is up here" into "the
> state is wherever you happen to find it".

## The principle

### The order is fixed

It costs nothing to follow and nothing to remember:

1. the docstring;
2. constants — `UPPER_CASE` names and `ClassVar`s, class-level facts;
3. fields — a dataclass's annotated names, a plain class's class attributes;
4. `__init__` and `__post_init__`;
5. the methods — properties among them, since a property is behaviour that reads the fields above.

Within one group nothing is prescribed: which constant comes first is the author's business, and a tight
run of related fields should stay tight.

### The pull the other way

It is always the same, and always a mistake: a class attribute added next to the method that uses it,
because that is where the author was typing. It reads well while the whole class is in your head.
Afterwards it is a fact about the object hidden inside its behaviour, and the next reader, looking for
what the class holds, has no way to know they reached the end of the list.

If the head of the class feels too long to read, the class is holding too much: split it. Do not solve a
crowded inventory by scattering it.

## Rules

- [ ] Declare a class's state at the top — constants, class attributes and fields above `__init__` and every method.
      _Move the assignment up to the head of the class, with the other state._
- [ ] Read a class's head in one fixed order: constants (`UPPER_CASE`, `Final`, `ClassVar`) first, then fields.
      _Move the constant above the first field._

## Worked example

### python-member-after-method

a constant, class attribute or field declared below a method — the class's state hidden among its behaviour

```py
----------[ Bad ]----------

class SupplierClient:
    def __init__(self, http) -> None:
        self.http = http

    def fetch(self, path: str) -> bytes:
        for _ in range(self.RETRIES):
            response = self.http.get(path)
            if response.ok:
                return response.body
        return b""

    RETRIES = 3

----------[ Good ]----------

class PatientSupplierClient:
    RETRIES = 3

    def __init__(self, http) -> None:
        self.http = http

    def fetch(self, path: str) -> bytes:
        responses = (self.http.get(path) for _ in range(self.RETRIES))
        return next((response.body for response in responses if response.ok), b"")
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/class-layout` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-member-after-method`, `python-member-out-of-order`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/class-layout`](../../backend/class-layout/SKILL.md) — the same discipline over PHP classes.
- [`python/value-objects`](../value-objects/SKILL.md) — a dataclass whose fields are its inventory — read at a glance.
