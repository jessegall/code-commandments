---
name: commandments-python-method-mood
description: "Naming a Python method or a `@property` — especially one that returns a `bool`, or one that changes the object and returns nothing. Read this BEFORE you name a method `hides`, `binds` or `spins`, and when a method-mood finding points here."
---

# Python method mood — an order, or a question

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> You do not describe an object to itself. A method you CALL is an order — `hide()`, not `hides()` — and
> a method that answers about state is a question — `is_hidden()`, not `binds()`. Two moods, and the call site
> tells you which one you are writing.

## The principle

### Read the call out loud

`panel.hide()` is an instruction you give an object: do this. `panel.hides()` is a sentence ABOUT the
object, narrated by nobody — it reads as documentation that wandered into the code. The imperative is not a
style preference; it is what a call IS.

### A question for state

A method or property that answers rather than acts gets the other mood: a question. A `bool` about the
object's own state is `is_shown`, `has_parent`, `can_retry`, `awaits_answer` — so `if el.is_shown:` reads
as the question it is. A bare `if el.shows():` reads as a claim, and the reader has to stop and work out
whether the call changes anything.

### Where the line falls

A predicate about a RELATION — one thing against another — is already a sentence with a subject and an
object, and the third person is right for it: `basket.contains(item)`, `pattern.matches(name)`,
`period.covers(day)`. The tell is the argument. With none, there is no second party, so it can only be
describing the receiver — and describing the receiver is what a question is for.

### What is never renamed

A name you did not choose is not a sin: a method overriding its base keeps the contract's spelling, and a
dunder is the language's. Only a verb the rule knows is judged, so a plural noun (`names`, `fields`) is
never mistaken for narration.

## Related skills

- [`backend/method-mood`](../../backend/method-mood/SKILL.md) — the same discipline over PHP methods.
- [`python/class-layout`](../class-layout/SKILL.md) — where a class keeps its state, beside what its methods are called.
