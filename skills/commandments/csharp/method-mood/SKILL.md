---
name: commandments-csharp-method-mood
description: "Naming a C# method or property — especially one that returns `bool`, or a method that changes the object and returns `void`. Read this BEFORE you name something `Hides`, `Binds` or `Spins`, and when a method-mood finding points here."
---

# C# method mood — an order, or a question

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> You don't describe an object to itself. A method you call is an order — `Hide()`, not `Hides()` — and
> a member that answers about the object's state is a question — `IsHidden`, not `Binds()`. There are two
> moods, and the call site tells you which one you are writing.

## The principle

### Read the call out loud

`panel.Hide()` is an instruction you give an object: do this. `panel.Hides()` is a sentence about the
object that nobody is saying — it reads like documentation that ended up in the code. Naming a call as an
order is not a style preference; it is what a call is.

### A question for state

A member that answers instead of acting gets the other mood: a question. A `bool` about the object's own
state is `IsShown`, `HasParent`, `CanRetry` — usually a property — so `if (panel.IsShown)` reads as the
question it is. `if (panel.Shows())` reads as a claim, and the reader has to stop and work out whether the
call changes anything.

### Where the line falls

A check about a relation — one thing against another — already has a subject and an object, and the third
person is right for it: `basket.Contains(item)`, `pattern.Matches(name)`, `period.Covers(day)`. The tell is
the argument. With no argument there is nothing to compare against, so the member can only be describing
the object itself — and describing the object is what a question is for.

### What is never renamed

A name you did not choose is not a sin: an override or an interface member keeps the contract's spelling.
Only a verb the rule knows is judged, so a plural noun (`Names`, `Fields`) is never mistaken for a sentence.

## Rules

- [ ] Name a `bool` about the object itself as a question: `IsBound`, `IsSpinning`, `HasParent`, `CanRetry`.
      _Rename it to a question — `Binds()` becomes `IsBound`, usually as a property._

## Worked example

### csharp-bare-state-predicate

a `bool` about the object's own state named as a claim — `Binds()`, `Spins` — where a question belongs

```cs
----------[ Bad ]----------

public bool Accepts() => open && freeSlots > 0;

----------[ Good ]----------

public bool IsAccepting => open && freeSlots > 0;
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/method-mood` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-bare-state-predicate`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/method-mood`](../../backend/method-mood/SKILL.md) — the same discipline in PHP.
- [`csharp/class-layout`](../class-layout/SKILL.md) — where a type keeps its state, next to what its members are called.
