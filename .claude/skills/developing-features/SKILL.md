---
name: developing-features
description: The playbook for building ANY feature in code-commandments itself (this maintenance project) — reuse the engine arsenal before writing anything, compose the fluent DSL (never NodeFinder/regex in a detector), put reusable logic on the right layer, gate a not-ready detector behind Unpublished and calibrate it clean before publishing, prove everything with tests. Read this BEFORE you start implementing a feature here.
---

# Developing features in code-commandments

This is the meta-skill for working ON this repo (not for a consumer project). It is the
order of operations and the non-negotiables. For the specifics of a detector, a scribe, a
fixture, or a release, defer to the focused skills linked at the end.

## 0. Before you write a line — REUSE

The single most-repeated mistake here is re-deriving logic the engine already has. **Every
feature starts by reading the arsenal** (CLAUDE.md → "The engine arsenal"): `Codebase`
selectors + whole-program methods (`extends`, `isEnum`, `isValueType`, `declarationMatch`,
`index()`/call graph, `valueFlow()`), the ~120 `AstNode`/`NodeMatch` predicates, `TypeName`,
and the `Ast\Support\*` analyses (`TypeResolver`, `ChainResolver`, `ReceiverResolver`,
`ValueFlow`, `FeatureEnvy`, …). If what you need is there, compose it. If it's close, EXTEND
it. Only if it's genuinely absent do you add a new tool — **on the right layer, never inline**
(see the layering rule in `detector-engine`). This is not a walk-only rule; it applies the
moment you start building anything.

## 1. Compose the engine — the two guardrails

- **No hand-rolled parsing/rewriting.** Detect through `Codebase → Query → AstNode`; rewrite
  through `Draft`/`Writer` with `Span` owning offset math. Banned in `Detectors`/`Scribes`/`Vue`:
  `preg_*`, `strpos`/`strrpos`/`strstr`, `ctype_*` (NoRegexInParsingLayerTest).
- **A detector composes the fluent DSL; it never `new NodeFinder()` and never hoards
  `PhpParser\Node` type-juggling** (DetectorsComposeTheEngineTest: no NodeFinder in a detector,
  ≤10 PhpParser imports). The moment you want a raw walk, a `type→string`, a `$this->x` reader,
  an "is it reassigned" check — STOP: that is a reusable primitive. Put it on
  `AstNode`/`TypeName`/`Codebase`/a `Support`, then compose it. Scribes rewrite through the one
  canonical `Writer`, never a bespoke rewriter.

Write everything **with intent to reuse** — assume the next detector needs the same predicate.

## 2. TDD, then prove on the fixture

Unit-test first (`Codebase::fromString(...)` → run the detector → assert `scope()`s), covering
the flag case AND the look-alikes it must NOT flag. Then prove it on the self-checking fixture
(`#[Sinful]`/`#[Righteous]` markers = the spec; ≥3 diverse + a righteous twin). See
`writing-detectors` and `detector-fixtures`.

## 3. Not ready to ship? Mark it `Unpublished` and CALIBRATE

A new detector almost always needs several calibrate→tighten rounds. Have the detector class
AND its sin implement `JesseGall\CodeCommandments\Unpublished` — both catalogs skip it, so it
stays out of `judge`, the fixture verifier, the generated docs, and every release while you
iterate. Unit-test it by instantiating it **directly**; calibrate by running it directly over a
scanned real codebase (a scratchpad probe: `Codebase::scan($root)` →
`new YourDetector()->find($cb)`; raise `-d memory_limit=3G` for large trees).

**Calibration is mandatory and it is where ideas die.** Read every hit against the
architecture, never against what the target happens to do. Volume ≠ false positive. The ONLY
thing that invalidates a detector is a genuine FP — a pattern *correct under the architecture*
that gets flagged. Tighten with a principled `reject` (walk the chain — resolve the real type,
classify value-vs-service, trace provenance — don't eyeball two files), or, if no AST signal
separates the sin from a valid look-alike (the difference is only author intent), **cut that
pattern.** When the hits read clean, delete `implements Unpublished`, add the fixtures, and it
enrols itself.

## 4. Ship it

Regenerate docs (`composer sins` + `composer readme`), run the whole suite
(`vendor/bin/phpunit tests` — the gate is phpunit; do NOT self-judge this repo), then
commit/merge/tag/push per `releasing` (a new semver tag per commit; no
Co-Authored-By trailer). Fix every sin/warning on files you touch.

## When to read what

| Skill | For |
|---|---|
| `package-overview` | the two-engine architecture, where things live |
| `detector-engine` | the fluent DSL + the layering rule (where a new helper goes) |
| `writing-detectors` | authoring a detector end-to-end |
| `detector-fixtures` | the `#[Sinful]` fixture spec + diversity/righteous rules |
| `writing-exemptions` | keeping a general rule general (the exemption registry) |
| `issue-triage` | resolving inbound `[detector-report]`/`[bug-report]` issues |
| `releasing` | commit/tag/push conventions |
