---
name: commandments-shared-arguments
description: "Read before adding a parameter, passing pieces of a value, or writing a result through an argument."
---

# Arguments: name the input and return the result

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A signature shows what the function needs and what the caller gets back.

## The principle

Resolve a lookup at the caller when the callee only needs the result. Give distinct operations distinct names.

## Rules

- [ ] Let the expression supply its return type.
      _Let the expression supply its return type._

## Commands

- `{{binary}} judge --skill=shared/arguments` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `redundant-arrow-return-type`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
