# code-commandments — guide for AI agents

**code-commandments is a compiler for architecture.** It judges a PHP codebase
against a set of architectural disciplines and reports each violation ("sin") as a
`file:line` that points at the skill which teaches the fix.

Two layers:

- **Skills** (`skills/`) — the teaching layer, one per architectural subject
  (`absence`, `value-objects`, `spatie-data`, `exceptions`, `enums-with-behaviour`,
  `laravel-idioms`, `role-vocabulary`, `concurrent-state`, `documentation`,
  `fix-at-the-source`). The source of truth for what good looks like.
- **Sin Detectors** (`src/Detectors/`) — thin finders over a fluent AST engine
  (`src/Ast/`). Each detector finds ONE sin and names the skill that fixes it; it
  has no fix logic. Auto-discovered by `Detectors\Catalog`.

Detectors are proven against a self-checking fixture (`tests/Fixtures/shop`) where
`#[Sinful(Detector::class)]` markers ARE the test spec.

## ⚠️ Building or changing a detector? LOAD THESE SKILLS FIRST — mandatory

Before you write or touch any detector, load these via the **Skill tool**:

1. **`writing-detectors`** — author a `Detector` end-to-end (start here).
2. **`detector-engine`** — the fluent AST DSL (`Codebase` → `Query` →
   `AstNode`/`NodeMatch`), the call graph, the variable trace, and where a new
   helper belongs (the layering rule).
3. **`detector-fixtures`** — the self-checking fixture: `#[Sinful]` = spec, the
   ≥3-diverse-scenarios rule, righteous twins.

They encode the cardinal rules: **AST/semantic signals over name/suffix matching**
(a name check is a smell to justify); **one check per `where()`/`reject()` line**;
TDD (red → green via `Codebase::fromString`); ≥3 genuinely-different fixtures plus
a righteous twin it must NOT flag; and **validate on a real codebase for false
positives** before shipping. Curate the best detectors — don't pad.

📍 **The roadmap is [`SINS.md`](SINS.md)** — every sin each skill teaches and which
have a detector. Flip a row to ✅ when a detector ships.

## Commands

| Command | Purpose |
|---|---|
| `bin/commandments judge [path] [--skill=NAME] [--detector=NAME] [--exclude=A,B]` | Scan a codebase; print sins grouped by the skill that fixes them, and write a `commandments-sins.md` checklist. Non-zero exit when sins are found. Files marked `@code-commandments-generated` are skipped. |
| `bin/commandments judge --no-checklist` / `--checklist=FILE` | Print only / retarget the checklist file. |
| `bin/commandments judge --list` | List every detector grouped by skill. |
| `bin/commandments install` | Wire a consumer: composer sync hook + a `UserPromptSubmit` reminder of the cardinal rule + gitignore, then sync. Idempotent. |
| `bin/commandments remind` | Emit the cardinal rule as a `UserPromptSubmit` hook payload (re-injects "trace to the source" every turn). |
| `vendor/bin/phpunit tests` | The suite — unit tests + the fixture verifier (`FixtureDetectorTest`). |

**Fixing sins — the checklist workflow.** A full scan is slow (~30s on a large
tree), so judge ONCE, then work the generated `commandments-sins.md` line-by-line:
read the section's skill, fix the sin at `file:line`, **delete that line**, repeat.
Don't re-run judge between fixes — re-run only at the end to confirm (a clean run
deletes the file).

## Conventions

- **AST/semantic detection over name matching** — always; derive the answer from
  the AST / resolved type, never a class/method/variable name or a hardcoded list.
- Commit messages carry **no `Co-Authored-By`** trailer.
- `deprecated/` holds the previous prophet/scroll system — **reference only**, do
  not build on it or port it one-to-one.
