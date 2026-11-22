---
name: developing-features
description: The playbook for building ANY feature in code-commandments itself (this maintenance project) — reuse what the rules, definitions and bridges already state before writing anything, write a rule as one JSON file over the generic tree (never a regex or a per-language copy), put a missing fact in the bridge, keep a not-ready rule unpublished and calibrate it clean before publishing, prove everything with tests in the capped dev container. Read this BEFORE you start implementing a feature here.
---

# Developing features in code-commandments

This is the meta-skill for working ON this repo (not for a consumer project). It is the order of operations and
the non-negotiables. For the specifics of a rule, a fixture, or a release, defer to the focused skills linked
at the end.

## 0. Before you write a line — REUSE

The single most-repeated mistake here is re-deriving logic that already exists. **Every feature starts by
reading what is there**: the 69 rule folders under `rules/`, the shared definitions under `rules/definitions/`
(`tree.json`, and a package's knowledge in `laravel.json`, `spatie.json`, …), the evaluator's operators
(`commandments rule schema`), the program relations (`rule/program.go`), and the contract's kinds and roles
(`contract/CONTRACT.md`). If what you need is there, call it. If it is close, EXTEND it. Only if it is
genuinely absent do you add it, **in the right place** (see `detector-engine`).

## 1. Compose the engine — the two guardrails

- **No hand-rolled parsing.** Every language arrives parsed by its own compiler and written as the generic
  tree. A regex over code is a missing fact: the bridge writes it and the contract documents it.
- **One rule, every language.** A rule is written once over the generic tree and judges every language in its
  `engine` list. A language never decides a rule; a check two rules need goes into a shared definition.

Write everything **with intent to reuse**: assume the next rule needs the same check.

## 2. TDD, then prove on the fixture

Try the rule first on a small sample (`commandments rule try <file> <path>`), covering the flag case AND the
look-alikes it must NOT flag; edge cases of one language go in `tests/rules/<language>`. Then prove it on the
self-checking fixtures (the sin markers are the spec; ≥3 diverse + a righteous twin + a fix, in every language
it judges). See `writing-detectors` and `detector-fixtures`. Every Go build and test runs through `scripts/dev`, scoped to the
packages you touched; .NET runs only in its capped container.

## 2b. Prove it the cheapest way that really proves it

Reaching for the whole binary when a package test covers the change is what makes this project feel slow. The
Go build is not the cost; the cost is `cli/parity` and `cmd/commandments`, which each build the binary and take
MINUTES, where a package test is a second or two. Both of those earn their time when the binary's own behaviour
is what changed. Nothing else should go through them.

| What you changed | What proves it | Roughly |
|---|---|---|
| a config key, a schema, how a config is read | `./cli/config` | 2s |
| a help line, a usage screen, a README table | `./cli/doc` | 6s |
| a report's wording, what a run says, what it keeps | `./cli/judge` | 2s |
| a hook's answer, a mark, what the journal is told | `./cli/hooks` | 2min, it judges real fixtures |
| what sync publishes, wires or withdraws | `./cli/sync` | 2s |
| a rule file | `./fixture/` (every rule on every fixture) | 1–2min |
| the evaluator or the generic tree | `./rule/`, `./contract/`, `./engine/` | 15–30s |
| a skill's own prose | `./skill/render/...`, which starts the C# bridges | 13min |
| the plugin's reader patterns and its manifest | nothing to build; they are data | 0s |
| the binary's own behaviour, end to end | `./cli/parity`, `./cmd/commandments` | minutes |

Three things follow. A shipped reader or a manifest is DATA: two reader fixes shipped on 2026-10-26 with no
build at all, proved by matching their patterns against real `judge` output. When a change's only cover is a
parity case, that is a gap rather than the price: write the package test beside the behaviour, and parity goes
back to proving what only it can, which is that the binary as a whole answers as its goldens say.

And measure a package before you trust a number here. These were measured on 2026-10-26, and the surprise was
the generators: `skill/render` and `skill/render/generate` take seven and thirteen minutes, because rendering a
skill's examples judges the fixtures, which starts the C# bridges. The cheap route is not always the obvious
one, so a package whose test you are about to avoid is worth timing once.

## 3. Not ready to ship? Mark it unpublished and CALIBRATE

A new rule almost always needs several calibrate→tighten rounds. Put `"unpublished": true` in its `sin` block:
every catalogue skips it, so it stays out of `judge`, the fixture verifier, the generated docs and every
release while you iterate. Try it directly with `commandments rule try <file> <path>`, on a scratchpad copy of
a real codebase, or build the tool and `bin/commandments judge ../some-app --sin=your-sin --no-checklist`.

**Calibration is mandatory and it is where ideas die.** Read every hit against the architecture, never against
what the target happens to do. Volume ≠ false positive. The ONLY thing that invalidates a rule is a genuine
false positive — a pattern *correct under the architecture* that gets flagged. Tighten with a principled check
(the resolved type, the role a value plays, where it came from — don't eyeball two files), or, if no tree
signal separates the sin from a valid look-alike (the difference is only author intent), **cut that pattern**,
or drop the language from the rule's `engine` list. When the hits read clean, delete `"unpublished"`, add the
fixtures, and it ships.

## 4. Ship it

The pre-commit hook regenerates the skills, the README tables and the command references and re-stages them
(`composer sins` does the same by hand). Run the tests of every package you touched through `scripts/dev`, then
commit per `releasing` (no attribution trailer). Fix every finding on files you touch. A fix is released once its
gate is green, without asking.

## When to read what

| Skill | For |
|---|---|
| `package-overview` | the engine, the bridges, where things live |
| `detector-engine` | what a rule reads, and where a missing fact goes |
| `writing-detectors` | authoring a rule end-to-end |
| `detector-fixtures` | the fixture spec + diversity/righteous/fixed rules |
| `writing-exemptions` | keeping a general rule general (exemptions as data) |
| `issue-triage` | resolving inbound `[detector-report]`/`[bug-report]` issues |
| `releasing` | commit, release build and tag conventions |
