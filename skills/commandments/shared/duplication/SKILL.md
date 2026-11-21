---
name: commandments-shared-duplication
description: "Read before copying a function, a compound condition, or a path to the same folder."
---

# Duplication: give repeated code one owner

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Code that represents one decision, mechanism or folder should say what it means in one place.

## The principle

Put the shared code where its data or responsibility lives. Pass the values that differ, and have each caller ask the owner for the answer.

## Rules

- [ ] Accept the value the callers already have and let the callee own the conversion.
      _Take the original typed value and convert it where it is used._
- [ ] Put the steps that define one job in a single function.
      _Extract the shared work, pass the differences as inputs, and call it from both sites._
- [ ] Write a shared function and call it from the places that need it.
      _Move the copied body to one owner and delegate to it from each caller._
- [ ] Keep the declarations as the source of truth for the classes that belong to the group.
      _Discover the implementations from their shared type, or let each class register itself._
- [ ] Share the body and pass what differs as parameters.
      _Extract the common function and give its varying inputs names._
- [ ] Name a folder in one place and share that name wherever code reaches it.
      _Put the folder in a shared constant or a method and use it to build paths within that folder._
- [ ] Give a repeated condition one name and have the callers use it.
      _Put a named predicate on the object that owns most of the condition and use it at each site._
- [ ] Give a repeated type test one name and use it at each site.
      _Extract the combined test to a predicate that describes which values it accepts._
- [ ] Use the vocabulary the parameter already has.
      _Replace the string with the named constant that carries the same value._

## Commands

- `{{binary}} judge --skill=shared/duplication` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `converted-argument`, `divergent-twin`, `duplicate-function`, `list-mirrors-folder`, `near-duplicate-function`, `repeated-folder-path`, `repeated-guard`, `repeated-type-guard`, `unnamed-vocabulary-literal`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 20 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
