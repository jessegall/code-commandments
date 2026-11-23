---
name: commandments-shared-method-mood
description: "Naming a method: one that acts (returns nothing or itself) or one that answers yes or no about its own object. Read this BEFORE you name a command in the third person (`hides()`, `entersTestMode()`) or a boolean as a bare verb (`spins()`, `binds()`), and when a method-mood finding points here."
---

# Method mood — a command is an order, a predicate is a question

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A method name has a grammatical mood. A call that acts is an order; a call that answers about the object is a
> question.
> Read this before you name a method, and when a method-mood finding points here.
> The examples are pseudocode: read them as the language you are writing.

## The principle

**Read the call out loud. A command is imperative, a yes-or-no about the object is a question.**

`panel.hide()` is an instruction you give an object: do this. `panel.hides()` is a sentence about the object,
narrated by nobody: the reader has to stop and work out whether the call changes anything or only reports. The
mood of the name is how a reader tells acting from answering without opening the method.

| The method | Mood | Reads as |
|---|---|---|
| acts: returns nothing, or returns itself | imperative | `hide()`, `enterTestMode()`, `openFor(user)` |
| answers yes or no about the object itself, takes no argument | question | `isShown()`, `hasParent()`, `canRetry()` |
| answers yes or no about a relation, takes what it is compared with | third person is right | `contains(item)`, `matches(name)`, `covers(date)` |

The third row is not an exception so much as plain English: `range.covers(date)` already has a subject and an
object. A yes-or-no with no argument has no second party, so it can only be describing the receiver, and that is
what a question is for.

A name you did not choose is never renamed: a method declared by an interface or a parent class, yours or a
framework's, keeps the contract's spelling. A plural-noun getter (`names()`, `fields()`) is a noun, not a narration.

## The sins

### narrated-command

A command named in the third person, or as a participle. The call site is giving an order, and the name describes
one instead. Name it in the imperative.

```pseudo
// Bad
panel.hides()
session.entersTestMode()
door.opensFor(user)

// Good
panel.hide()
session.enterTestMode()
door.openFor(user)
```

### bare-state-predicate

A yes-or-no about the object's own state, named as a bare verb. `if (wheel.spins())` reads as a claim, or worse, as
something that starts the wheel. Make it a question: `is…`, `has…`, `can…`.

```pseudo
// Bad
if (wheel.spins()) { … }
if (field.binds()) { … }
if (job.retries()) { … }

// Good
if (wheel.isSpinning()) { … }
if (field.isBound()) { … }
if (job.canRetry()) { … }
```

## In your language

- **PHP, TypeScript, Go.** `hide()`, `isShown()`, `hasParent()`, `canRetry()`.
- **Python.** The same moods in snake case: `hide()`, `is_shown()`, `has_parent()`. A `@property` that answers yes or
  no is still a question: `panel.is_shown`.
- **C#.** `Hide()`, and a yes-or-no as a property: `IsShown`, `HasParent`, `CanRetry`.

## Commands

- `{{binary}} judge --skill=shared/method-mood` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `bare-state-predicate`, `narrated-command`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
