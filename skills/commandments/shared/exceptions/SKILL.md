---
name: commandments-shared-exceptions
description: "Writing a `throw`/`raise`, a `try`/`catch`/`except`, or an exception class in any language. Read this BEFORE an empty catch, a catch that returns null or rethrows what it caught, a new failure thrown inside a catch, or `throw new Exception(\"…\")`, and when an exceptions finding points here."
---

# Exceptions — fail loud, named, with the cause

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A failure is loud, named and carries its cause. Code that catches a failure does something about it,
> or does not catch it at all. These rules hold the same in every language, so every language is judged
> by them the same way.

## The principle

### Never swallow a failure

A `catch` whose body is empty, or only returns `null`, `false`, an empty string or an empty collection,
turns a failure into a value that looks like an answer. The caller cannot tell "nothing there" from
"something broke". Let the failure travel, or catch it at one named boundary that logs it and moves on.

### Catch only to do something

A `catch` whose whole body throws back the failure it caught does what writing no clause does. Delete
it, or give it work: roll back, release what was held, or throw a failure of your own with this one as
its cause.

### A wrapped failure keeps its cause

When a `catch` throws a new failure in place of the caught one, hand the caught one over as the cause
(`previous: $e`, `new X(msg, e)`, `{ cause: e }`, `raise X(...) from e`). Passing only its message
drops the trace that says where it started.

### Throw a named failure, and let it write its own message

`throw new Exception("…")` and `raise RuntimeError("…")` say nothing a caller can catch by name. Give
the failure a type of its own, and a named constructor that takes the values involved and writes the
sentence, so the message is written once, where the failure is defined, not at every throw.

## Rules

- [ ] Throw a named failure, never the language's catch-all one.
      _A failure type of its own, with a named constructor that takes the values involved and writes the message._
- [ ] Pass the values involved to a named constructor on the failure; never write the message at the throw.
      _A named constructor on the failure (`::for($order)`, `ForOrder(order)`) that writes the sentence once._
- [ ] Catch a failure only to do something about it: handle it, add what the caller needs, or let it pass by writing no clause.
      _Delete the clause and let the failure travel, or give it work: roll back, release what was held, or throw a failure of your own with it as the cause._
- [ ] Let a failure throw, or surface it named with its cause; never swallow a catch into an empty body or an absent value.
      _Rethrow it wrapped with the caught failure as its cause, or catch, log and skip at one named boundary._
- [ ] When wrapping a caught failure, hand the original over as its cause; never drop the trace.
      _Pass the caught failure as the cause (`previous: $e`, `new X(message, e)`, `{ cause: e }`, `raise X(...) from e`)._

## Commands

- `{{binary}} judge --skill=shared/exceptions` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `generic-exception`, `message-at-throw`, `rethrown-unchanged`, `swallow-catch`, `wrapping-without-cause`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 5 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/exceptions`](../../php/exceptions/SKILL.md) — what is particular to PHP's exceptions.
- [`python/exceptions`](../../python/exceptions/SKILL.md) — what is particular to Python's exceptions.
- [`csharp/exceptions`](../../csharp/exceptions/SKILL.md) — what is particular to C#'s exceptions.
