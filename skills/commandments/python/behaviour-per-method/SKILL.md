---
name: commandments-python-behaviour-per-method
description: "A Python parameter that selects WHICH behaviour runs rather than feeding one. When a function's whole body is `if flag: … else: …`, it is two functions sharing a name, and every call reads `render(order, True)`. Read this before adding a `bool` parameter, before widening a required parameter to `X | None = None` so that leaving it out means 'all of them', and when a call passes a bare `True`/`False`."
---

# Python behaviour per method — never a flag that picks

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> `render(order, True)` — True *what*? The caller already knows which of the two things it wants;
> the flag is that decision, flattened into a truth value and handed over for the callee to unpack again.

## The principle

A parameter is something a function **works with**. A flag argument is something it works *around*: it
arrives, is tested once, and decides which half of the body runs. The two halves were never one function —
they share a name and a signature, and nothing else.

- **`send(order, True)` is unreadable.** Nothing at the call says what `True` means.
- **The halves cannot evolve apart.** A parameter one half needs becomes one the other must ignore.
- **It hides how the code is used.** Split, `send_draft` has three callers and `send_final` thirty.
- **Flags multiply.** Two bools are four behaviours in one body, and usually three were meant.

So **name the two behaviours**: `render(order, True)` becomes `render_compact(order)` and
`render_full(order)`, and whatever they share becomes a private function both call.

### A keyword does not split it

`render(order, *, compact: bool = False)` makes the call say `compact=True`, and that is better than a bare
`True` — but the body still runs one of two jobs behind one name. Keyword-only is how to pass a flag that
TUNES a behaviour; a flag that SELECTS one is still two functions.

### The disguise: `None` that means "all of them"

A required parameter widened so that leaving it out asks a different question — `fields(kind: str)` becomes
`fields(kind: str | None = None)`, and `fields()` now means "every one". That is two questions behind one
name, selected by an ABSENCE at the call, which says even less than a bare `True`. The fix is the same:
`fields_of_kind(kind)` and `fields()`.

### What is NOT this sin

- **A flag that is DATA the function stores or forwards** — `set_visible(visible)`: the value is the point.
- **A flag that tunes ONE behaviour** — `parse(text, strict=True)`, where strictness changes what counts as
  an error inside one pass rather than choosing another pass.
- **A guard at the top** — `if force: return self._overwrite()` is a guard clause (see `python/flow`). The
  smell is a body that is *nothing but* the two-way branch.
- **An enum that names the choice** — `render(order, Density.COMPACT)` already says what it means.

### The tell

The whole body is `if flag: … else: …`, a `match flag:` with a `True` and a `False` case, or
`if kind is None: … else: …` on a parameter that used to be required. Ask what you would call each half on
its own. If both have an obvious name, they are already two functions — give them their names.

## Rules

- [ ] Split a function whose body is one branch on a flag into two NAMED functions — never make a call say `True`, and never widen a required parameter to `X | None = None` so that leaving it out means 'all of them'.
      _Name each half for what it does (`render_compact()` / `render_full()`), with any shared middle as a private function both call._

## Worked example

### python-flag-argument

a function whose whole body branches on a `bool` parameter — or on whether an optional one was given — two functions sharing one name

```py
----------[ Bad ]----------

def render(self, compact: bool) -> str:
    if compact:
        return f"{self.number}: {len(self.lines)} lines"
    else:
        return "\n".join([self.number, *self.lines])

----------[ Good ]----------

class InvoiceSheet:
    def __init__(self, number: str, lines: list[str]) -> None:
        self.number = number
        self.lines = lines

    def render_compact(self) -> str:
        return f"{self.number}: {len(self.lines)} lines"

    def render_full(self) -> str:
        return "\n".join([self.number, *self.lines])
```

## Commands

- `vendor/bin/commandments judge --skill=python/behaviour-per-method` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-flag-argument`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/behaviour-per-method`](../../backend/behaviour-per-method/SKILL.md) — the same discipline over PHP methods.
- [`python/enums`](../enums/SKILL.md) — when the choice has more than two cases, it is an `Enum` — and the behaviour belongs on it.
- [`python/flow`](../flow/SKILL.md) — an early return that guards is the shape this one is NOT.
