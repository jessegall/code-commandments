---
name: commandments-shared-enums
description: "Read before repeating a declared set of cases in a switch, membership check, or chain of comparisons."
---

# Enums: give a closed set one source

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> The declared type owns the names for a closed set of values.

## The principle

Translate strings to the declared type at the boundary. Keep shared behavior keyed by the named cases, where a new case calls for one decision.

## Rules

- [ ] Declare the cases as an enum.
      _Declare the cases as an enum._
- [ ] Give that group of cases a named test on the enum.
      _Give that group of cases a named test on the enum._
- [ ] Use the named cases or a named group of cases.
      _Use the named cases or a named group of cases._
- [ ] Keep the trait beside the cases it describes.
      _Keep the trait beside the cases it describes._
- [ ] Dispatch on the declared type and its named cases.
      _Dispatch on the declared type and its named cases._
- [ ] Put the behavior on the type that owns it.
      _Put the behavior on the type that owns it._

## Commands

- `{{binary}} judge --skill=shared/enums` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `const-class-enum`, `enum-case-or-chain`, `in-array-mirrors-enum`, `side-table-trait`, `string-match-mirrors-enum`, `type-switch`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 6 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
