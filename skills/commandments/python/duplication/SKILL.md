---
name: commandments-python-duplication
description: "Copying a function or method body from one Python module into another — the same `load`/`render`/`validate` written a second time under another name, or a near-copy that differs only in the path, the key or the message it uses. Read this BEFORE pasting a `def` you already wrote somewhere else, and when a `duplicate-python-function` finding points here. The fix is one function both call, parameterised by whatever actually differs."
---

# Python duplication — one behaviour, one home

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A function written twice is one decision living in two places. The day it has to
> change, one copy gets the fix and the other keeps the bug — and nothing in either file
> says the other exists. In a Python codebase it happens one module at a time: each
> handler grows its own `_read_json`, each command its own `_format_row`, until the
> behaviour the program depends on is spread across files that do not know about each other.

## The principle

### A copy is a decision made twice

Two functions with the same body are the same code whatever they are called — and the
case worth catching is exactly when they are NOT called the same, because then nobody
searching for one finds the other. Hoist the body to ONE home and let every caller use it:

- a module-level function in the package both callers already import, when it only computes;
- a method on the class that owns the data, when it reads one object's attributes;
- a method on a shared base class, when two subclasses each wrote the same override.

### A near-copy is a missing parameter

Two bodies with the same control flow that differ only in a literal — a path, a dict key,
an error message, the text of an f-string — are one function waiting for an argument.
Name what differs and pass it; do not keep two copies because the difference "is only a
string". The string is the parameter.

### What is NOT duplication

Short bodies are alike by coincidence: a one-line delegate, a property, a `return
self._x` cannot be hoisted into anything smaller than itself. Neither can two `__init__`s
of two different classes, two stubs (`pass`, `...`, `raise NotImplementedError`) that
leave the body to a subclass, or two lookup tables that call nothing and only return
constants — those are data, not procedure. Duplication is a body of real substance, twice.

## Rules

- [ ] Hoist a function body written twice into one shared function, and call it from both places.
      _Move the body to one function in a module both callers import (or a method on the class that owns the data), and replace every copy with a call to it._
- [ ] Merge two functions that differ only in a literal into one, and pass what differs as a parameter.
      _Name the literal that differs, make it a parameter of one shared function, and call that from both places._

## Worked example

### duplicate-python-function

Copy-pasted code — two+ Python functions or methods with an identical body, formatting, comments and docstrings aside

```py
----------[ Bad ]----------

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

----------[ Good ]----------

# in layout.py
def describe_lines(lines: list[Line]) -> list[str]:
    return [f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}" for line in lines if line.quantity > 0]

# in layout.py
def rows(self, lines: list[Line]) -> list[str]:
    return describe_lines(lines)

# in layout.py
def line_items(self, lines: list[Line]) -> list[str]:
    return describe_lines(lines)
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/duplication` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `duplicate-python-function`, `near-duplicate-python-function`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../../backend/fix-at-the-source/SKILL.md) — the root instinct — one decision, made once, where it is born.
- [`typescript/duplication`](../../typescript/duplication/SKILL.md) — the same discipline over TypeScript modules and Vue components.
