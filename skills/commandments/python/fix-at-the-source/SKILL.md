---
name: commandments-python-fix-at-the-source
description: "Writing an `__init__` that calls out to something it was handed, a module-level or class-level variable that functions write to, or a fix for a Python finding that is tempting to patch where it surfaced. Read this BEFORE making a constructor do work, keeping state in a `global` or a class attribute, or adding a check at a call site, and when a `python-constructor-side-effect` or `python-mutable-static-state` finding points here."
---

# Python fix at the source — where a value is born, not where it hurts

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A wrong value is almost always wrong where it was born. The call site that trips over it is only
> where it surfaced; patching there adds a check, the next caller trips on the same problem, and the root cause
> is never fixed. In Python the same move applies to objects and state: build an object without changing the
> world, and keep changing state on an instance someone owns, so every effect has a place you can see.

## The principle

### Trace it upstream before you change a line

A finding is a symptom. Before adding an `if x is None`, a default or a `try` where it surfaced, ask
where the value came from and walk back until you reach the place that made it. Fix that place, and
the check you were about to write — and every copy of it — is no longer needed.

### An `__init__` builds; it does not act

A constructor says what the object IS: it stores what it was given and derives what it needs. When it
tells a collaborator to DO something — warm a cache, register itself, open a connection — and throws
the answer away, merely creating the object changes the world, in a line of code that reads like
bookkeeping. Keep the collaborator as an attribute and act on it from the method someone calls, at the
moment they chose.

### State that changes lives on an instance

A module-level variable written by a `global`, or a class attribute assigned from a method, is state
every caller shares and nobody passes. Who changed it, and when, is written nowhere. Hold changing
state on an instance, pass that instance to the code that needs it, and the dependency is in the
signature where a reader can see it.

## Rules

- [ ] Let `__init__` establish what the object is; never let building one change anything outside it.
      _Keep the collaborator as a field and act on it from the method that someone actually calls._
- [ ] Put shared behaviour in one place, so a step that must always happen can't be forgotten in a copy.
      _Make the shorter function call the longer one, or have both call one shared function._
- [ ] Hold changing state on an instance someone owns and passes; never write a `global` or a class attribute from a function.
      _Move the state onto an object, and hand that object to the code that reads and changes it._

## Worked example

### python-constructor-side-effect

an `__init__` that tells a collaborator to act and throws the answer away — merely building the object has an effect outside it.

```py
----------[ Bad ]----------

class CardPlugin:
    def __init__(self, registry, fee: float) -> None:
        self.fee = fee
        registry.add("card", self)

    def charge(self, amount: float) -> float:
        return amount * (1 + self.fee)

----------[ Good ]----------

# in payment_plugins.py
class CardPayments:
    def __init__(self, fee: float) -> None:
        self.fee = fee

    def charge(self, amount: float) -> float:
        return amount * (1 + self.fee)

# in payment_plugins.py
def install_card_payments(registry, fee: float) -> CardPayments:
    payments = CardPayments(fee)
    registry.add("card", payments)
    return payments
```

The other 2 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/fix-at-the-source` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-constructor-side-effect`, `python-divergent-twin`, `python-mutable-static-state`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 3 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../../backend/fix-at-the-source/SKILL.md) — the same discipline over PHP, where every other skill defers to it.
- [`python/absence`](../absence/SKILL.md) — deciding absence where a value is born is this rule applied to `None`.
