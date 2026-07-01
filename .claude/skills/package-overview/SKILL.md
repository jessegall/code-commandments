---
name: package-overview
description: The map of the code-commandments package — what the two layers are (Skills teach, Sin Detectors find), the two parse engines (backend PHP AST in src/Ast, frontend Vue in src/Vue) that share ONE detector DSL, where each concern lives, and which skill to read next. Read this first when orienting in the codebase or unsure where a thing belongs.
---

# code-commandments — the package map

**A compiler for architecture.** It judges a PHP **and** Vue codebase against architectural
disciplines and reports each violation ("sin") as a `file:line` that points at the skill teaching
the fix. Built agent-first: the output is a worklist + a curriculum, not warnings to triage.

## Two layers

- **Skills** (`skills/commandments/{backend,frontend}/<slug>/SKILL.md`) — the teaching layer, one
  per architectural subject. The source of truth for what "good" looks like. A detector's `Sin`
  points at one by FQCN. Generated rows (the "when it fires" table, Bad→good) come from the sin's
  `description` + the fixture markers — `composer sins` regenerates them; never hand-edit.
- **Sin Detectors** (`src/Detectors/{Backend,Frontend}/*Detector.php`) — thin finders over the
  fluent AST engine. Each finds ONE sin and names the skill that fixes it; no fix logic. Auto-
  discovered by `Detectors\Catalog`. Sins are first-class classes under `src/Sins/{backend,frontend}/`
  (discovered by `Sins\Catalog`); a detector references its `Sin`, never declares one inline.

## Two engines, ONE detector DSL

- **Backend** — `src/Ast/` over PHP (nikic/php-parser): `Ast\Codebase` → `Ast\Query` → `NodeMatch`.
- **Frontend** — `src/Vue/` over `.vue` SFCs (our own tokenizer + a real JS-expression AST,
  `Vue\Expr\*`): `Vue\Codebase` → `Vue\Query` → `ElementMatch`.

They parse different languages but a detector **reads the same either way** — the same fluent query
(selector opens a `Query`, `where`/`reject` narrow it one check per line, a terminal returns matches
that know their `file:line`). Both `Codebase`s implement the shared `src/Codebase.php` interface;
`Backend\Detector` and `Frontend\Detector` both extend the root `src/Detector.php` (the split is a
typed contract, not duplication — PHP forbids narrowing a param type in an implementation).
**Everything that isn't "how do I parse / detect / fix" is engine-agnostic** (the CLI commands, the
runner, `Testing\FixtureTestCase`, `Diversity`). Never write the same machinery twice.

## Where things live

| Concern | Home |
|---|---|
| Backend engine / frontend engine | `src/Ast/` · `src/Vue/` |
| Detectors · Sins · Skills | `src/Detectors/{Backend,Frontend}` · `src/Sins/{Backend,Frontend}` · `src/Skills/` |
| Per-package AST knowledge (FQCNs stated once) | `src/Ast/{Laravel,Spatie,Concurrent,PhpTypes}/*Node`, package sins/detectors in `Backend/<Pkg>/` |
| Exemptions (keep general rules framework-agnostic) | `src/Packages/` (`Exemption`, `Exemptable`, `Package`, `Exemptions`) |
| Auto-fixers (the `repent`/`hints` scribes) | `src/Scribes/` + `src/Cli/Hints/` |
| CLI commands | `src/Cli/` + the `bin/commandments` dispatch |
| Self-checking fixtures | `tests/Fixtures/backend` (PHP `#[Sinful]`) · `tests/Fixtures/frontend` (`<!-- @sin -->`) |

## CLI (see `bin/commandments`)

`judge` (scan; scope from `$config->paths(...)` in `.commandments/config.php`), `repent` (run the
scribes), `hints` (Spatie Data `@method` hints), `scaffold`, `config [reindex]`, `disable`/`enable`,
`exemptions`, `report`, `feature-request`, `install`, `sync`, `remind`.

## Read next

- [[writing-detectors]] — author a detector end-to-end · [[detector-engine]] — the fluent DSL ·
  [[detector-fixtures]] — the self-checking fixture · [[writing-exemptions]] — keep a general rule
  framework-agnostic · [[releasing-and-propagating]] — ship + propagate.
- `CLAUDE.md` is the maintained, authoritative guide (more detail than this map).
