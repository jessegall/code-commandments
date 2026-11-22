---
name: package-overview
description: The map of the code-commandments package — what the layers are (Skills teach, rule files name a sin and find it), the one engine every language reaches through its own bridge and the one generic tree every rule reads, where each concern lives, and which skill to read next. Read this first when orienting in the codebase or unsure where a thing belongs.
---

# code-commandments — the package map

**A compiler for architecture.** It judges a PHP, Vue, TypeScript, Python, C# and Go codebase against
architectural disciplines and reports each violation ("sin") as a `file:line` that points at the skill teaching
the fix. One static Go binary; built agent-first: the output is a worklist + a curriculum, not warnings to
triage.

## Two layers

- **Skills** (`skill/<engine>/`, rendered into `skills/commandments/<engine>/<slug>/SKILL.md`) — the teaching
  layer, one per architectural subject; the source of truth for what "good" looks like. Generated parts (the
  "when it fires" table, Bad → good) come from the rule files and the fixture markers — the pre-commit hook
  and `composer sins` regenerate them; never hand-edit a rendered file.
- **Rules** (`rules/<engine>-<subject>/<name>.json`) — one JSON file per sin: its name and aliases, the skill
  that fixes it, its description, and the `find` that locates it over the generic tree. No fix logic. Every
  file under `rules/` is embedded in the binary and enrols itself.

## One engine, a bridge per language

Each language is parsed by its own compiler in a small bridge — PHP by php-parser (`bridge/php`), Vue and
TypeScript by TypeScript's own compiler (`bridge/frontend`), Python by mypy (`bridge/mypy`), C# by Roslyn
(`bridge/roslyn`), Go by go/types (`bridge/gotree`) — which writes the generic tree (`contract/CONTRACT.md`).
The engine loads every stream into one `engine.Codebase`, and the rule evaluator (`rule/`) reads it **the same
whatever the language**. A language supplies plain facts only, in its bridge; it never decides a rule.
**Everything that isn't "how do I parse" is shared** (the CLI, the report, the fixture harness, the rule
evaluator, the recurrence and twin analyses, the syntax hash). Never write the same machinery twice.

## Where things live

| Concern | Home |
|---|---|
| The generic tree · its reader | `contract/` (`CONTRACT.md`, the schema, the reader) · `engine/` |
| The bridges and how the tool runs them | `bridge/` (the PHP and frontend bundles embedded; mypy's venv built on first use; the C# bridge fetched from the release) |
| Rules · their loader and evaluator · Skills | `rules/` · `rule/` (`library.go`, `evaluate.go`, the registry in `detector.go`) · `skill/<engine>` |
| A package's own knowledge (stated once) | `rules/definitions/{laravel,spatie,…}.json`, its rules in `rules/php-<package>/` |
| Exemptions (keep general rules framework-agnostic) | data in the rule files and `rules/definitions/` |
| CLI commands | `cli/` (one package per verb), dispatched by `cli/commands` from `cmd/commandments` |
| A consumer's own rules | `.commandments/custom/<Name>.json`, the same format, read by `cli/custom` through the same loader |
| Self-checking fixtures | `tests/Fixtures/{backend,frontend,python,csharp,go}`, proven by `fixture/rules_test.go` |
| One language's rule edge cases | `tests/rules/<language>` |

## CLI

`commandments --help` lists every verb, grouped; `commandments <verb> --help` is its page. The ones in daily
use: `judge`, `info`, `rule` (`explain`, `try`, `prove`, `schema`), `make`, `sync`, `disable`/`enable`,
`report`, `feature-request`, `layers`, and the service `roslyn-serve`.

## Read next

- [[writing-detectors]] — author a rule end-to-end · [[detector-engine]] — what a rule reads ·
  [[detector-fixtures]] — the self-checking fixtures · [[writing-exemptions]] — keep a general rule
  framework-agnostic · [[releasing]] — ship a change.
- `CLAUDE.md` is the maintained, authoritative guide (more detail than this map); `docs/requirements.md` says
  what a user's machine needs per language.
