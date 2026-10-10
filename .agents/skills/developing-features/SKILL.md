---
name: developing-features
description: The playbook for building ANY feature in code-commandments itself (this maintenance project) — reuse the engine arsenal before writing anything, compose the fluent query (never a regex or a raw tree walk in a detector), put reusable logic on the right layer, gate a not-ready detector behind Unpublished() and calibrate it clean before publishing, prove everything with tests in the capped dev container. Read this BEFORE you start implementing a feature here.
---

# Developing features in code-commandments

This is the meta-skill for working ON this repo (not for a consumer project). It is the order of operations and
the non-negotiables. For the specifics of a detector, a scribe, a fixture, or a release, defer to the focused
skills linked at the end.

## 0. Before you write a line — REUSE

The single most-repeated mistake here is re-deriving logic the engine already has. **Every feature starts by
reading the arsenal** (CLAUDE.md → "The engine arsenal"): the `engine.Codebase` selectors, `engine.Match`, each
language's decorator (`php.Node`'s ~200 predicates and the others), and the analyses (`php.IndexOf`,
`php.ExpressionType`, `php.ValueFlowOf`, `php.Trace`, `engine.Recurring`, `engine.DivergentTwins`, …). If what
you need is there, compose it. If it's close, EXTEND it. Only if it's genuinely absent do you add a new tool —
**on the right layer, never inline** (see the layering rule in `detector-engine`).

## 1. Compose the engine — the two guardrails

- **No hand-rolled parsing or rewriting.** Every language arrives parsed by its own compiler; detect through
  `engine.Codebase → engine.Query → engine.Match`, rewrite through the scribes' drafts with `engine.Source`
  owning the offset math. A regex over code in a detector or a scribe is a missing engine tool. A fact the
  tree lacks belongs in the bridge and the contract.
- **A detector composes the query; it never walks the tree by hand.** The moment you want a raw walk, a
  type-to-string, a field reader, an "is it reassigned" check — STOP: that is a reusable primitive. Put it on
  the language's decorator or in an analysis, then compose it. Scribes rewrite through the one writer, never a
  bespoke one.

Write everything **with intent to reuse** — assume the next detector needs the same predicate.

## 2. TDD, then prove on the fixture

Unit-test first through the language's source builder (`frontendtest.FromSource`, `pythontest.FromSource`,
`csharptest.FromSource`), covering the flag case AND the look-alikes it must NOT flag. Then prove it on the
self-checking fixture (the sin markers are the spec; ≥3 diverse + a righteous twin + a fix). See
`writing-detectors` and `detector-fixtures`. Every Go build and test runs through `scripts/dev`, scoped to the
packages you touched; .NET runs only in its capped container.

## 3. Not ready to ship? Mark it unpublished and CALIBRATE

A new detector almost always needs several calibrate→tighten rounds. Give the detector AND its sin the method
`Unpublished()` (`catalog.Unpublished`) — every catalog skips it, so it stays out of `judge`, the fixture
verifier, the generated docs, and every release while you iterate. Unit-test it by calling it **directly**;
calibrate by running it over a scanned real codebase (a scratchpad probe:
`scan.Walk([]string{root}, source.Excluded{}).Load()` → `YourDetector{}.Find(codebase)`), or build the tool and
`bin/commandments judge ../some-app --sin=your-sin --no-checklist`.

**Calibration is mandatory and it is where ideas die.** Read every hit against the architecture, never against
what the target happens to do. Volume ≠ false positive. The ONLY thing that invalidates a detector is a genuine
false positive — a pattern *correct under the architecture* that gets flagged. Tighten with a principled
`Reject` (resolve the real type, classify value-vs-service, trace provenance — don't eyeball two files), or, if
no tree signal separates the sin from a valid look-alike (the difference is only author intent), **cut that
pattern.** When the hits read clean, delete `Unpublished()`, add the fixtures, and it enrols itself.

## 4. Ship it

The pre-commit hook regenerates the skills, the README tables and the command references and re-stages them
(`composer sins` does the same by hand). Run the tests of every package you touched through `scripts/dev`, then
commit per `releasing` (no attribution trailer). Fix every finding on files you touch. A fix is released once its
gate is green, without asking.

## When to read what

| Skill | For |
|---|---|
| `package-overview` | the engines, the bridges, where things live |
| `detector-engine` | the fluent query + the layering rule (where a new helper goes) |
| `writing-detectors` | authoring a detector end-to-end |
| `detector-fixtures` | the fixture spec + diversity/righteous/fixed rules |
| `writing-exemptions` | keeping a general rule general (the exemption registry) |
| `issue-triage` | resolving inbound `[detector-report]`/`[bug-report]` issues |
| `releasing` | commit, release build and tag conventions |
