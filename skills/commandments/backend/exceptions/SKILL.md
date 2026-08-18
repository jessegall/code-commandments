---
name: commandments-backend-exceptions
description: "How to fail — throw NAMED exceptions via static factories (`Thing::for($x)`), never a message string at the throw site, and never swallow a failure into null/false/[]/Option::none(). Read this FIRST whenever you write a `throw`, a `try`/`catch`, an exception class, or are deciding what to do when something goes wrong. Fail hard and named, at the source."
---

# Exceptions — fail hard, fix once

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> **Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual failure is a
> five-minute fix. A swallowed one is a silent wrong result you chase for a week.

## The principle

A failure is information. The instant it happens, it knows the most it will ever know — *what* broke and
*with what values*. Throw that knowledge **loudly, by type, at the source**. Every line you put between
the failure and its surfacing — a `catch` that returns null, a default that papers over it, a bare
`Exception("...")` — destroys information and moves the eventual debugging session further from the cause.

This is [`fix-at-the-source`](../fix-at-the-source/SKILL.md) for the error channel — and the place the
[`absence`](../absence/SKILL.md) skill sends you when "missing" turns out to be a broken state.

### The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer rethrows forever. It means failures travel *up* to **one explicit
boundary** that is allowed to absorb them — and even there, absorbing is **observable**, never silent. The
canonical shape: an untrusted-input decoder that catches per item, **logs**, and skips, then fails hard if
*nothing* survived.

Inside the system, invariants throw. At the *one* untrusted edge, you catch-log-skip. That's fail-hard
*and* resilient — not graceful-and-silent.

## Rules

- [ ] Throw a NAMED domain exception, never a bare SPL `Exception`/`RuntimeException`.
      _A named exception class with a static `::for($values)` factory that writes the message._
- [ ] Pass domain VALUES to a named factory; never assemble the message string at the throw site.
      _A static `::for($values)` factory on the exception that builds the sentence._
- [ ] Let a failure throw, or surface it named with the cause; never swallow a catch into `null`/`false`/`[]`/`none()` or an empty body.
      _Rethrow wrapped (`previous: $e`), or catch-log-skip at one named boundary._
- [ ] When wrapping a caught exception, pass the original as `previous`/cause — never drop the stack trace.
      _Pass the caught exception as `previous: $e`._

## Worked example

### generic-exception

`throw new <bare SPL>` (RuntimeException/LogicException/…) instead of a named type

```php
----------[ Bad ]----------

public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

----------[ Good ]----------

public function carrierNameNamed(Shipment $shipment): string
{
    return $shipment->carrier?->displayName()
        ?? throw CarrierMissing::for($shipment->id);
}
```

The other 3 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/exceptions` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `generic-exception`, `message-at-throw`, `swallow-catch`, `wrapping-without-cause`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `wrapping-without-cause`. Review it with `--dry-run` first.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — surface the failure where it's born.
- [`backend/absence`](../absence/SKILL.md) — absence routes "missing = broken state" here for the *how* of throwing; this skill routes "swallowed failure became an empty value" back there as the inverse smell.
