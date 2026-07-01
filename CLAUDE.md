# code-commandments — guide for AI agents

**code-commandments is a compiler for architecture.** It judges a PHP codebase
against a set of architectural disciplines and reports each violation ("sin") as a
`file:line` that points at the skill which teaches the fix.

Two layers:

- **Skills** (`skills/commandments/{backend,frontend}/<slug>/`) — the teaching layer,
  one per architectural subject, split by engine. Backend: `backend/absence`,
  `backend/value-objects`, `backend/spatie-data`, `backend/laravel-idioms`,
  `backend/fix-at-the-source`, … Frontend: `frontend/vue-components`,
  `frontend/vue-control-flow`. The slug (engine-prefixed) is what a detector's
  `skill()` returns. The source of truth for what good looks like.
- **Sin Detectors** (`src/Detectors/`) — thin finders over a fluent AST engine
  (`src/Ast/`). Each detector finds ONE sin and names the skill that fixes it; it
  has no fix logic. Auto-discovered by `Detectors\Catalog`.

Detectors are proven against a self-checking fixture (`tests/Fixtures/shop`) where
`#[Sinful(Detector::class)]` markers ARE the test spec.

### Two front-ends, ONE detector DSL — non-negotiable

There are two parse engines: the **backend** AST over PHP (`src/Ast/`, php-parser)
and the **frontend** AST over Vue `.vue` SFCs (`src/Vue/`, our own tokenizer — built
from scratch, no Node, no v3 reuse). They parse different languages, but a detector
**MUST read the same** either way: a frontend detector composes the **exact same
fluent query syntax** as a backend one — a selector opens a `Query`, `where`/`reject`
narrow it (one check per line), a terminal returns rich matches that know their
`file:line`. Same shape, same rules (AST/semantic over names; compose the engine,
never poke the tree), just over Vue `Element`s instead of PHP nodes. If a frontend
detector doesn't look like a backend detector, the engine is wrong — fix the engine,
not the detector. (Frontend scope is the `frontend.canon`, sibling to `backend.canon`.)

**The two engines are the SAME system; ONLY the detection/parse algorithm differs.**
Backend and frontend each have a codebase, a fluent query, detectors, scribes, a
canon, and a self-checking fixture — and that is not a coincidence to maintain by
hand, it is the architecture. Everything that is NOT "how do I parse / how do I
detect / how do I fix" must be engine-agnostic and operate on base types: the CLI
commands (`judge`, `scribe`) don't care backend-vs-frontend, the runner/report work
on the abstract `Finding` (already just strings), the fixture harness
({@see FixtureTestCase}) and the diversity engine ({@see Diversity}) are shared, the
canon is one mechanism (`backend.canon` / `frontend.canon`). **NEVER write the same
machinery twice for the two engines — if something is backend-only today, abstract it
behind a base type so the frontend reuses it; do not copy it.** When you reach for
copy-paste between engines, stop: the shared thing belongs in a base class / shared
`Testing`/`Cli` component, parameterised by the one hook that genuinely differs.

**Everything the backend does, the frontend does the same way.** A frontend detector
follows the identical process: build it AST-first, prove it on the `.vue` self-
checking fixture (`tests/Fixtures/shop-frontend`), calibrate on the consumers' real
`.vue`, and curate. The Vue side has the matching layers — `Vue\Codebase` →
`Vue\Query` → `Vue\ElementMatch` (the template AST), `Vue\Expr\*` (a real JS-
expression AST: lexer + Pratt parser over binding/interpolation expressions), the
`Vue\Detector` base (sibling of `Detectors\Detector`, both extend the root
`Detector`), `Detectors\Frontend\*` detectors, and `Scribes\Frontend\*` scribes
(backend scribes live in `Scribes\Backend\*`). Keep that symmetry: a thing
belongs in the `Backend`/`Frontend` folder of its concern.

### 🚫 NO regex for structure — build an engine tool instead

Reaching for a regex to read code structure (a member chain, a method call, a
binding, an equality, nesting depth) is almost always the wrong choice — it's the
hack the backend never makes (it has php-parser). The frontend has its OWN parsers:
the `Vue\` tokenizer for templates and `Vue\Expr\Parser` for the JS inside bindings.
**Parse it into the AST and query the AST.** If the predicate you need isn't there,
add a tool to the engine (a method on `Element` / `Expr`, a selector on the
`Query`/`Codebase`) so detectors compose it fluently — never scrape it with a regex
in the detector. Regex is for genuine text/delimiter scanning only (splitting `{{ }}`
delimiters, lexing tokens) — not for understanding the code. A regex over an
expression is a smell that the engine is missing a tool; write the tool.

The `#[Sinful]` markers fixture is the spec for backend; the `<!-- @sin Detector -->`
comment fixture is the spec for frontend. Lean on the fixtures + a focused unit test
per mechanism, exactly like the backend.

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
bin/commandments judge ../app-a/src        --sin=your-sin --no-checklist
bin/commandments judge ../app-b/app --sin=your-sin --no-checklist
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

📍 **Sins are first-class.** Each sin is its OWN class under `src/Sins/{backend,frontend}/`
(name + skill slug + description), discovered by `Sins\Catalog` — the sin twin of
`Detectors\Catalog`. A detector *references* its sin (`sin(): Sin { return new ArrayBag(); }`),
never declares one inline. `judge --sin=<name>` filters to it (the retired `--detector`).
The generated `SKILL.md` "when it fires" rows project from the registered sins.

## Commands

| Command | Purpose |
|---|---|
| `bin/commandments judge [path] [--skill=NAME] [--sin=NAME] [--exclude=A,B] [--changes] [--branch[=BASE]] [--parallel=N]` | Scan a codebase; print sins grouped by the skill that fixes them, and write a `.commandments/sins.md` checklist. Non-zero exit when sins are found. Files marked `@code-commandments-generated` are skipped. `--changes` reports only sins in files changed/created in the working tree; `--branch[=BASE]` instead scopes to files new/changed on the current branch vs BASE (default `main`) — committed and uncommitted, via the merge-base, no worktree needed. The whole path is still parsed so cross-file detectors stay correct; only the output is scoped. `--parallel=N` runs the detectors across N forked workers (default 8, capped at CPU cores; `--parallel=1` = sequential, also the fallback where `pcntl` is unavailable). |
| `bin/commandments judge --no-checklist` / `--checklist=FILE` | Print only / retarget the checklist file. |
| `bin/commandments judge --list` | List every detector grouped by skill. |
| `bin/commandments hints [path] [--changes\|--branch[=BASE]] [--dry-run[=FILE]]` | Auto-fix Spatie `Data` magic surface: rename non-`from…` object factories to `from<Type>` + rewrite call sites to `::from(...)`, and regenerate `@method from(...)`/`collect(...)` docblock hints. **Default applies; `--dry-run[=FILE]` previews a unified diff.** `--changes`/`--branch` scope to touched files but force **docblock-only** mode (no renames — a rename's call sites can live outside the scope); renaming is whole-tree only. |
| `bin/commandments repent [path] [--changes\|--branch[=BASE]] [--dry-run[=FILE]] [--only=NAME]` | Auto-fix sins — the CLI verb that RUNS the **Scribes** (`src/Scribes/`; "scribe" is the code, `repent` is the command). Two kinds, one command: the maintenance Scribes over the PHP AST (Spatie Data hints, redundant arrow-fn return types; scope-aware) **and** the `Repentable` detectors' scribes over the Vue components (extract a component, hoist a `v-if` chain to `<SwitchCase>` — fed each detector's findings). Default applies; `--dry-run[=FILE]` previews a unified diff; `--only=NAME` runs one rewriter (Scribe or frontend Detector name). (`hints` is the focused Data-only entry.) |
| `bin/commandments report --reason="…" [--detector=NAME] [--file=PATH] [--line=N]` | File a GitHub issue (via `gh`): a `[detector-report]` (false positive / wrong rule) when `--detector` is given, else a `[bug-report]` for a global bug. Only `--reason` is required. |
| `bin/commandments feature-request --title="…" --reason="…"` | File a `[feature-request]` GitHub issue proposing a new/changed rule (via `gh`). |
| `bin/commandments disable <sin>` / `enable <sin>` | Toggle a rule in the project's `.commandments/config.php`: resolve the sin id (lenient) to its `Sin` class and add/remove it in the `$config->disable(...)` call. Edited through the AST ({@see Cli\ConfigFile}), never text-scanned; the file stays valid PHP and the human's own `register`/`configure` lines are untouched. |
| `bin/commandments install` | Wire a consumer: composer sync hook + a `UserPromptSubmit` reminder of the cardinal rule + gitignore, then sync. Idempotent. |
| `bin/commandments remind` | Emit the cardinal rule as a `UserPromptSubmit` hook payload (re-injects "trace to the source" every turn). |
| `vendor/bin/phpunit tests` | The suite — unit tests + the fixture verifier (`FixtureDetectorTest`). |

**Fixing sins — the checklist workflow.** A full scan is slow (~30s on a large
tree), so judge ONCE, then work the generated `.commandments/sins.md` line-by-line:
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
