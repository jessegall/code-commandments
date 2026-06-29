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

### ⛔ Calibrate against the real consumer apps — MANDATORY before any detector ships

A green fixture proves the detector *can* fire; it does **not** prove it's right. A
detector is not done until it has been run against the real consumer codebases and
its hits read by eye:

```
bin/commandments judge ../app-a/src        --detector=YourDetector --no-checklist
bin/commandments judge ../app-b/app --detector=YourDetector --no-checklist
```

Open the flagged `file:line`s and judge each **against the skill/our architecture —
NEVER against what the consumer project happens to do.** The consumer apps are not
ground truth: they contain real sins, code done wrong, and old style since changed
your mind on. So a widespread pattern there is **not "convention" that excuses a
finding** — and **volume alone proves nothing**: 400 hits can be 400 genuine sins
(e.g. an app that never marks its DTOs `final`). Do not soften or drop a detector
because it fires a lot.

The ONLY thing that invalidates a detector is a genuine **false positive** — a
pattern that is *correct under our architecture* yet gets flagged. When those
appear, **tighten with a principled `reject` (never a name list), or drop the
detector entirely.** Some ideas die here: if no AST signal separates the sin from a
*legitimately valid* look-alike (the difference is only author *intent*), the
detector is not viable — report why and cut it. That — not its hit count — is why
`DataNonDispatchingFactoryDetector` was dropped: `AiMessage::user()` is a valid
named constructor indistinguishable from a mis-prefixed factory. Calibrate every
time, not "later".

📍 **The roadmap is [`SINS.md`](SINS.md)** — every sin each skill teaches and which
have a detector. Flip a row to ✅ when a detector ships.

## Commands

| Command | Purpose |
|---|---|
| `bin/commandments judge [path] [--skill=NAME] [--detector=NAME] [--exclude=A,B] [--git] [--branch[=BASE]]` | Scan a codebase; print sins grouped by the skill that fixes them, and write a `commandments-sins.md` checklist. Non-zero exit when sins are found. Files marked `@code-commandments-generated` are skipped. `--git` reports only sins in files changed/created in the working tree; `--branch[=BASE]` instead scopes to files new/changed on the current branch vs BASE (default `main`) — committed and uncommitted, via the merge-base, no worktree needed. The whole path is still parsed so cross-file detectors stay correct; only the output is scoped. |
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
- **Overlap is allowed — do NOT strip a detector to avoid it.** One piece of code
  can genuinely be several sins (e.g. set-property-then-`save()` is BOTH
  `ModelMutationAtCallSite` AND read-then-mutate `FeatureEnvy`). Two detectors
  firing on the same `file:line` is correct when both sins are real — each points
  at a different skill/fix. `#[Sinful]` is `IS_REPEATABLE`: a fixture method may
  carry multiple markers (and a detector may have more than 3 marked locations —
  ≥3 *diverse* is the floor, not a cap). Never weaken or delete a valid detection
  just because another detector also flags it; double-mark the fixture instead.
- Commit messages carry **no `Co-Authored-By`** trailer.
- `deprecated/` holds the previous prophet/scroll system — **reference only**, do
  not build on it or port it one-to-one.
