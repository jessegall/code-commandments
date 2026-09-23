---
name: commandments-python-type-honesty
description: "Annotating an attribute or a parameter `X | None` that the design always has set, reading it back with `x.y if x else …` or `getattr(x, \"y\", default)`, filling a required `str` field with `\"\"` to satisfy a signature, saving `self.x` to a local and restoring it later, or a `@property` that returns a constant. Read this BEFORE you widen a type to make something pass, and when a type-honesty finding points here."
---

# Python type honesty — the annotation must not lie

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> An `X | None` that is never actually `None` is a lie the whole codebase pays for. Every reader has
> to prove the value is there again — `if self.batch is None`, `self.batch.permits(sku) if self.batch else
> False` — and one of those fallbacks answers a question for a state that cannot happen. Make the annotation
> say what the design guarantees.

## The principle

### A certain value is typed certain

When a value is always present where it is used, the annotation says so: a required parameter, an
attribute set in `__init__` and never `None`, a field of a frozen dataclass. Hedging it as `X | None`
"because it is filled in later", or keeping per-call state on `self`, pushes the certainty back onto
every reader, who re-establishes it with defensive code. The defensive code is the symptom; the cure is
upstream, in the type.

### Not every `None` is a lie

This is the complement of `python/absence`. Absence says: model a value that is genuinely missing
honestly — `None` with a check where it is born, an empty collection, a raise. Type honesty says: do not
manufacture a missing value the design does not have. A collaborator that may legitimately be absent and
is injected once is an absence decision, not a lie.

### The tell

You are re-proving, on every read, something the design already guarantees: `x.y if x else <fake>` on
your own attribute, a fallback branch that cannot be reached, a `previous = self.x … self.x = previous`
round trip. Ask whether the value is ever actually absent here. If it is not, the annotation is lying —
move the value into the signature, a required attribute or a value object, and delete the defence.

## Rules

- [ ] A `@property` must derive from the object; a value it never reads `self` for is a class attribute.
      _`kind = "box"` on the class — or a `ClassVar` — and the property goes._
- [ ] Make the invariant certain instead of masking it: pass the per-call value as a parameter, or hold it non-optional from construction.
      _Hand the value to the methods that need it (`covers(period, day)`) or build a per-call object holding it, and delete the fallback._
- [ ] If a field is used as present everywhere, its type says so: make it required, and fail at construction on a real miss.
      _Drop the `| None` and the `= None` default, and make every constructor hand the value over._
- [ ] A required field means the caller has the value; never fill one with `""` to satisfy the signature.
      _Fetch the real value — or split a narrower dataclass that only promises what this caller knows._
- [ ] Pass a per-call value as a parameter; don't save and restore one of your own attributes around the call.
      _Hand the value down as an argument — or a small per-call object — and the attribute, its save and its restore disappear._

## Worked example

### python-constant-property

an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one.

```py
----------[ Bad ]----------

@property
def lifetime_seconds(self) -> int:
    """How long a session stays valid."""
    return 60 * 60 * 8

----------[ Good ]----------

class ShopSession:
    LIFETIME_SECONDS: ClassVar[int] = 60 * 60 * 8

    def __init__(self, user: str) -> None:
        self.user = user

    def expires_after(self, started: int) -> int:
        return started + self.LIFETIME_SECONDS
```

The other 4 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/type-honesty` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-constant-property`, `python-masked-invariant`, `python-phantom-nullable`, `python-placeholder-filled-data`, `python-scratch-state-restore`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 5 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/type-honesty`](../../backend/type-honesty/SKILL.md) — the same discipline over PHP.
- [`python/absence`](../absence/SKILL.md) — the complement: absence models a genuine maybe-missing; this kills a fake one.
- [`python/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is born, not defended at every read.
