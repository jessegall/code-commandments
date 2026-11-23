---
name: commandments-shared-dependencies
description: "Read before importing anything between modules or layers."
---

# Dependencies: keep imports going in one direction

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> An import states which module a part of the project depends on.

## The principle

When two modules need the same contract, give that contract a home both may import.

## Rules

- [ ] Take a contract for the cross-module dependency.
      _Take a contract for the cross-module dependency._
- [ ] Use the accessible contract for identical signatures. Extend it to add the remaining signatures.
      _Check that the contracts promise the same behaviour before reusing the existing one._
- [ ] Use the implementation directly unless the interface is a boundary.
      _Use the implementation directly unless the interface is a boundary._
- [ ] Give the dependency one direction.
      _Move the shared contract to a module both can use._
- [ ] Read what the declared type guarantees. When only some implementers carry the member, give them a contract that declares it and type the receiver by that contract.
      _Declare the member on the carrying types' contract and use that contract at the access._

## Commands

- `{{binary}} judge --skill=shared/dependencies` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `concrete-cross-module-dependency`, `interface-overlap`, `lone-implementer-interface`, `namespace-cycle`, `undeclared-member`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
