---
name: commandments-shared-documentation
description: "Read before writing a comment or changing a declaration's documentation."
---

# Documentation: explain the code as it is

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A useful comment tells the reader why the code takes its shape or what contract a caller can rely on.

## The principle

Use names and types for what the code already says. Keep decisions and their history in the change record, and give any explanation that belongs in the source one place near its declaration.

## Rules

- [ ] Explain why the code is written this way today.
      _Move the history to the change record._
- [ ] Keep the type description short.
      _Move supporting detail to the place where it is needed._
- [ ] Keep documentation for information the signature does not already say.
      _Remove the redundant tags and leave any useful explanation._
- [ ] Keep references tied to existing declarations.
      _Correct the target or remove the reference._
- [ ] Say what the code does.
      _Keep a comment only when it explains a real constraint._
- [ ] Give the statement clear names and let it speak for itself.
      _Delete the comment or explain the reason for the statement._
- [ ] Keep one doc comment per declaration.
      _Merge useful text into one doc comment._

## Commands

- `{{binary}} judge --skill=shared/documentation` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `archaeology-comment`, `bloated-docblock`, `ceremony-docblock`, `dangling-doc-reference`, `negative-space-comment`, `restated-comment`, `stacked-docblock`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 7 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
