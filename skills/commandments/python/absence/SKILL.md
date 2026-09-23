---
name: commandments-python-absence
description: "Modelling a value that might not be there in Python — a return typed `X | None` or `Optional[X]`, a `return None` for \"not found\", an `if x is None:` or `x or default` at a call site, a `.get(key, default)`, or deciding between raising, returning an empty collection and returning `None`. Read this BEFORE writing any of them."
---

# Python absence — decide "missing" where the value is born

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> `None` is not a way to model absence. It is the *absence of a decision* about absence.
> Make the decision once, where the value is born, so no reader downstream has to guess what
> "not there" means.

## The principle

### Ask in order, stop at the first yes

1. **Can it actually be missing, or is "missing" a broken state?** A config the program cannot
   run without, a record a caller just created, a key the code itself wrote — absence there is a
   failure. **Raise a named exception.** Do not return `None` for it.
2. **Does "nothing" have a natural empty form?** A search with no hits is `[]`; a mapping with no
   entries is `{}`; a behaviour with nothing to do is a Null Object — an instance whose methods do
   nothing. Return that, and every caller loops or calls with no special case.
3. **Is it a genuine "look for it; it may miss" that more than one caller handles?** Then an
   annotated `X | None` is honest — it is Python's `Option`: a type checker makes every caller
   handle the missing case, and the check belongs at each caller *because* each one decides
   something different. If every caller writes the same `if result is None: raise …` or `or
   default`, the decision was the producer's: move it there.

### `or default` answers the wrong question

`name = user.name or ""` fills a required slot with a value nobody chose, and loses the question:
was the name missing, or genuinely empty? `x or []` also swallows `0`, `False` and `""` — every
falsy value, not just `None`. If the value can be missing, handle that case; if it cannot, do not
defend against it.

### The one honest `None`

A single local lookup checked right where it is produced — one caller, one `is None`, done — needs
no ceremony. The smell is a `None` that **travels**: returned, passed on, and re-checked at every
place it lands.

## Rules

- [ ] Say a value may be missing in its type; never default a `str` to `""` and read that blank back as "missing".
      _`x: str | None = None`, asked `x is None` — so the blank is not a value every reader has to decode._
- [ ] Ask about absence directly (`x is not None`); never default a value only to compare it against that same default.
      _Write both checks explicitly — `x is not None and x != ""` — or make the value non-optional at its source so only one question is left._
- [ ] Don't spread a conditional into an empty collection to include an entry; give the target a factory that drops what is absent, and pass the value by name.
      _A `@classmethod` factory — `Payload.of(note=note)` — whose body drops `None` keyword arguments, so an absent value simply vanishes with no conditional._
- [ ] Never fill an argument with an invented `""`, `0` or `False` on absence — handle the missing case, or make the value certain where it is born.
      _Decide at the source: raise when the value must be there, or pass `None` on to a parameter that admits it. A real default (`or "EUR"`) is a choice, not an invention._
- [ ] Default an optional callback to a no-op in the signature; don't take `None` and normalise it in the body.
      _Default the parameter to a named no-op — `def ignore(*_): pass`, then `on_retry: Callable[[int], None] = ignore` — and call it unconditionally._

## Worked example

### python-blank-string-default

`x: str = ""` standing in for absence — then asked `x == ""`, `not x` or `if x:` in its own scope

```py
----------[ Bad ]----------

def book(self, parcel: str, tracking: str = "") -> str:
    if not tracking:
        tracking = self.api.issue(parcel)
    return self.api.book(parcel, tracking)

----------[ Good ]----------

def book_tracked(self, parcel: str, tracking: str | None = None) -> str:
    if tracking is None:
        tracking = self.api.issue(parcel)
    return self.api.book(parcel, tracking)
```

The other 4 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/absence` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-blank-string-default`, `python-cancelled-fallback`, `python-conditional-spread`, `python-invented-default`, `python-nullable-callback`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 5 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/absence`](../../backend/absence/SKILL.md) — the same decision on the PHP backend, with `Option`.
- [`python/exceptions`](../exceptions/SKILL.md) — when "missing" is a broken state, the named exception to raise.
