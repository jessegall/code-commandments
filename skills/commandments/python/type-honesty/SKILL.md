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

- [ ] A required field means the caller has the value; never fill one with `""` to satisfy the signature.
      _Fetch the real value — or split a narrower dataclass that only promises what this caller knows._

## Worked example

### python-placeholder-filled-data

`Card(title=…, body="")` — a dataclass field required as `str` handed the blank to satisfy the signature, a value the type cannot catch

```py
----------[ Bad ]----------

def teaser(product) -> Teaser:
    return Teaser(product.name, "")

----------[ Good ]----------

# in product_teasers.py
@dataclass(frozen=True)
class ProductTeaser:
    name: str
    subtitle: str | None = None

# in product_teasers.py
def product_teaser(product) -> ProductTeaser:
    return ProductTeaser(product.name)
```

## Commands

- `vendor/bin/commandments judge --skill=python/type-honesty` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-placeholder-filled-data`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/type-honesty`](../../backend/type-honesty/SKILL.md) — the same discipline over PHP.
- [`python/absence`](../absence/SKILL.md) — the complement: absence models a genuine maybe-missing; this kills a fake one.
- [`python/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is born, not defended at every read.
