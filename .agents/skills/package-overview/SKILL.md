---
name: package-overview
description: The map of the code-commandments package — what the layers are (Skills teach, Sins name, Sin Detectors find), the one Go engine every language reaches through its own bridge and the one query every detector composes, where each concern lives, and which skill to read next. Read this first when orienting in the codebase or unsure where a thing belongs.
---

# code-commandments — the package map

**A compiler for architecture.** It judges a PHP, Vue, TypeScript, Python and C# codebase against
architectural disciplines and reports each violation ("sin") as a `file:line` that points at the skill teaching
the fix. One static Go binary; built agent-first: the output is a worklist + a curriculum, not warnings to
triage.

## Three layers

- **Skills** (`skill/<engine>/`, rendered into `skills/commandments/<engine>/<slug>/SKILL.md`) — the teaching
  layer, one per architectural subject; the source of truth for what "good" looks like. Generated parts (the
  "when it fires" table, Bad → good) come from the sins' descriptions and the fixture markers — the pre-commit
  hook and `composer sins` regenerate them; never hand-edit a rendered file.
- **Sins** (`sins/<engine>/`) — each its own type: a name, the skill slug that fixes it, a description.
- **Sin Detectors** (`detectors/<engine>/`) — thin finders over the engine. Each finds ONE sin and returns it;
  no fix logic. Each registers itself in `init()`; `registry` imports them all.

## One engine, a bridge per language

Each language is parsed by its own compiler in a small bridge — PHP by php-parser (`bridge/php`), Vue and
TypeScript by TypeScript's own compiler (`bridge/frontend`), Python by mypy (`bridge/mypy`), C# by Roslyn
(`bridge/roslyn`) — which writes the generic tree (`contract/CONTRACT.md`). The engine loads every stream into
one `engine.Codebase`, and a detector **reads the same whatever the language**: a selector opens an
`engine.Query`, `Where`/`Reject` narrow it one check per line, `Get` returns `engine.Match`es that know their
`file:line`. A language's own knowledge lives on its decorator (`engine/<lang>`). **Everything that isn't "how
do I parse / detect / fix" is engine-agnostic** (the CLI, the report, the fixture harness, the recurrence and
twin analyses, the syntax hash). Never write the same machinery twice.

## Where things live

| Concern | Home |
|---|---|
| The engine · a language's view of it | `engine/` · `engine/{php,frontend,typescript,vue,python,csharp}` |
| The bridges and how the tool runs them | `bridge/` (the PHP and frontend bundles embedded; mypy's venv built on first use; the C# bridge fetched from the release) |
| Detectors · Sins · Skills | `detectors/<engine>` · `sins/<engine>` · `skill/<engine>` |
| A package's own knowledge (stated once) | `engine/php/{laravel,spatie,concurrent,phptypes}`, its sins and detectors in `detectors/backend/<pkg>/` |
| Exemptions (keep general rules framework-agnostic) | `engine/php/packages` |
| Auto-fixers (the `repent`/`hints` scribes) | `scribes/` · `cli/hints` |
| CLI commands | `cli/` (one package per verb), dispatched by `cli/commands` from `cmd/commandments` |
| A consumer's own rules | `rule/` (a detector written as data), read from `.commandments/custom/` by `cli/custom` |
| Self-checking fixtures | `tests/Fixtures/{backend,frontend,python,csharp}` |
| The PHP tool's recorded answers the tests still hold Go to | `engine/php/testdata/oracle`, the scribes' `testdata`, `docs/parity.md` |

## CLI

`commandments --help` lists every verb, grouped; `commandments <verb> --help` is its page. The ones in daily
use: `judge`, `repent`, `hints`, `info`, `make`, `sync`, `disable`/`enable`, `report`, `feature-request`,
`layers`, and the services `journal-serve` and `roslyn-serve`.

## Read next

- [[writing-detectors]] — author a detector end-to-end · [[detector-engine]] — the fluent query ·
  [[detector-fixtures]] — the self-checking fixture · [[writing-exemptions]] — keep a general rule
  framework-agnostic · [[releasing]] — ship a change.
- `CLAUDE.md` is the maintained, authoritative guide (more detail than this map); `docs/requirements.md` says
  what a user's machine needs per language.
