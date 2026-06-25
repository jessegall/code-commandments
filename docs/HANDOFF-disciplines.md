# Handoff — disciplines migration (continue here)

Picking up the v3 "compiler-like discipline prophets" work. Read this, then
`docs/disciplines.md` (the rule spec) and `docs/discipline-migration-tracker.md`
(the queue + retirement map). Memory: `project_discipline_migration`,
`project_v3_doctrines`.

---

## 1. The philosophy (why we're doing this)

The old shape is **many loose, single-purpose prophets** — one tiny check each.
They over/under-fire in isolation, they double-report on the same code, and there's
no notion of "fix the root cause first." We are migrating to a small set of
**discipline prophets**: one prophet per *concern*, exposing **disjoint verdicts**
(the way `OptionDisciplineProphet` already does ADOPT / NEVER-NONE / WRAP-THEN-UNWRAP).

The governing principles — apply these to every discipline:

1. **One concern, many verdicts.** A discipline owns a single question ("how is
   absence modelled?", "is each value typed honestly at the boundary?") and answers
   it with several verdicts that can't contradict each other.
2. **AST / real type semantics, never name lists.** Classify by reflecting over the
   effective constructor / signature / inheritance, the `CodebaseIndex`, the shape
   detectors (`RegistryShape`/`SetShape`/`RoleInference`). A `const FOO_BASES = [...]`
   name list is a smell to justify, not a default. Where the real signal is genuinely
   unreachable (e.g. a framework `Response` ancestry not loadable at scan time), a
   short-name fallback is allowed **and must be commented as such**.
3. **Retire, don't delete.** Existing prophets are *re-homed* into a discipline, not
   removed. A standalone prophet is retired **only once a discipline verdict actually
   reproduces its detection** — not before.
4. **NEVER remove a verdict from a discipline prophet.** Only ADD new verdicts and
   REFINE existing ones (add guards, add use-following). Refining ≠ removing. This is
   a hard standing rule (see memory `project_discipline_migration`).
5. **Use-following over guessing.** When a structural signal is ambiguous (is this
   all-nullable DTO lazy or genuinely optional?), follow how the value is *used*
   across the codebase via the index, rather than firing on shape alone. V2-REFINE is
   the worked example: it gates PHANTOM-NULLABLE on a consumer that consumes a field
   as a required value, which dropped the `ScheduleSpec` false positive while keeping
   `RawGraphPayload`.
6. **Prove zero false positives on real code.** Every verdict is TDD'd (a fire case +
   an FP-guard case, written *first*), then run on the real consumers (workflows +
   app-b) and eyeballed. A finding the author can't act on (framework
   contract, deliberate poly-form) is a false positive — carve it out.
7. **Coarse → fine, single-owner.** Within a discipline the root-cause verdict is
   presented before the nitpick; across disciplines a rule has exactly one owner and
   the other defers via **one** `RootCauseMap` edge (never hand-override both ends).

This is how good code stays good: the tool behaves like a **compiler that knows
exactly where a value is mistyped**, not a linter throwing a pile of small warnings.

---

## 2. What exists now (state)

- **`TypeHonestyProphet`** (`src/Prophets/Backend/`) — the first discipline
  (BoundaryTyping), **shipped as v3.13.0** (commit `bb84d04`, pushed). 9 verdicts,
  66 unit tests, full suite green, 0 FP on both consumers. See the verdict table in
  `docs/discipline-migration-tracker.md`.
- It is **registered in the scroll config** (`config/commandments.php` +
  `commandments.self.php`) so `judge` runs it.
- The grind Stop-hook (`.claude/hooks/grind-disciplines.sh`, gitignored) drove the
  build verdict-by-verdict; its marker is cleared (loop is done).

### THE GAP (the first task below)
`TypeHonestyProphet` is **NOT in `src/Doctrines/DoctrineRegistry.php`**. Registering a
prophet in the scroll makes `judge` run it; adding it to a **Doctrine** is what wires
it into the cascade — band-based severity defaults, the pilgrimage walk order, and the
coarse→fine ordering. Right now TypeHonesty is homeless w.r.t. the doctrines.

---

## 3. Immediate next steps

### 3a. Add TypeHonesty to a doctrine  ← START HERE
`DoctrineRegistry::all()` returns `Doctrine` objects; each is `new Doctrine(name,
bands)` where `bands` is `list<list<class-string>>`, coarse (band 0) → fine. A prophet
is one class-string entry in one band.

The boundary/typing/coalesce cascade already lives in the **`totality`** doctrine
(band 0 = `PreferNativeTypedAccessor` boundary head; later bands = source totality →
dead coalesce → coalesce-factory → `T_*::coalesce` nitpick, incl. `WideUnionType`,
`NoCoalesceOnNonNullable`, `PreferTypeCoalesce`).

**Recommended:** put `TypeHonestyProphet::class` in **`totality` band 0** (the
boundary head), because its coarsest verdicts (V1 FAKE-REQUIRED, V5
REQUIRED-BUT-NULLABLE — both sins, "the type lies about the contract") are the most
root-cause boundary violations and should cascade *above* the finer coalesce nitpicks
below them.

**Design wrinkle to decide (flag to the owner):** a Doctrine band entry is
*per-prophet*, but a discipline prophet emits BOTH coarse sins (V1/V5) and fine
warnings (V6/V7). Banding the whole prophet at band 0 makes all its findings share
band-0 precedence. Options: (a) accept band-0 placement (simplest; its sins dominate);
(b) extend the Doctrine model to band *per-verdict*; (c) eventually give BoundaryTyping
its own dedicated doctrine once the `[covers:]` prophets re-home into it. Start with
(a); raise (b)/(c) when re-homing begins.

After editing: run `vendor/bin/phpunit tests/Doctrine` (CorpusTest / EngineTest) and
the full suite. Check whether any test asserts "every prophet is homed in a
doctrine-or-singleton" — if so, this addition satisfies it.

### 3b. Wire the V6 ↔ WideUnionType single-owner edge
`V6 BOOL-UNION` (`T|false`) and `WideUnionTypeProphet` (any 2+-member union) BOTH fire
on `User|false` → double-report. Add ONE edge in `RootCauseMap::relations()` so
BoundaryTyping/V6 owns the `T|false` found-or-not shape and WideUnion defers there.
Do NOT delete WideUnion (it owns every other union shape). Confirm with `RootCauseMapTest`.

### 3c. Propagate v3.13.0 to consumers
`composer update-consumers` (batch, hooks-bypassed, COMMIT-ONLY — do not push the
consumers). Runs `sync --after=previous` to register TypeHonesty into each consumer's
`commandments.php`. Per the standing rule, do this right after release.

### 3d. Re-arm the next discipline
Next per the retirement map: **ErrorException** (new — the swallowed-error family from
the decoder review: broad-catch→absence, discarded cause, dual channels) or
**AbsenceOption** (extend `OptionDisciplineProphet`). To restart the autonomous grind:
seed the new verdicts into the grind queue in `docs/discipline-migration-tracker.md`
and `touch .claude/grind-disciplines-active`. Same TDD-first, FP-zero-on-consumers loop.

---

## 4. Refactor `TypeHonestyProphet` (do this before it grows further)

The prophet works and is fully tested, but it grew **monolithic** — one ~1000-line
class holding 9 verdicts plus a large shared toolbox (constructor resolution,
receiver-is-class resolution, use-following, empty-literal detection, type-node
predicates). It's the right behaviour but the wrong shape; the next discipline will
re-create the same toolbox. Refactor goals (behaviour-preserving — the 66 tests are
the safety net, keep them all green):

1. **Extract the shared resolution toolkit** into reusable support classes (e.g.
   `Support/Resolvers/Ast/`): effective-constructor resolution (reflection→AST→index),
   `receiverIsClass` / `varsAssignedFromClass` (the "is this expression of type C?"
   engine), the empty-literal / nullable-type-node predicates, `functionLikeContaining`.
   These are discipline-agnostic and the AbsenceOption/ErrorException prophets will
   want them.
2. **Split each verdict into its own detector** (a small class or method object with a
   single `find(ast, content): Finding[]`), with `TypeHonestyProphet::judge()` as a
   thin orchestrator that runs them and merges. This makes "add a verdict" a new file,
   not a bigger class, and keeps per-verdict tests pointed at per-verdict units.
3. **Keep the verdict catalogue declarative** so the doctrine-banding question (3a)
   becomes tractable — a verdict knows its own tier/severity.
4. Do it as a **pure refactor PR**: no behaviour change, suite stays green, then a new
   patch tag.

This refactor is also the template for how *every* discipline prophet should be
structured, so it's worth doing once, well, now — before ErrorException copies the
current shape.

---

## 5. Conventions / gotchas

- Commits: no `Co-Authored-By`; new semver tag every commit (minor for a feature,
  patch for a fix; never major without asking); push commit + tag together.
- `--no-verify` is fine here — the package self-judge is a test harness, not a gate.
- Run a new prophet on BOTH consumers via the package CLI; note the `--path` harness
  does NOT build the cross-file index over the target (the package's `backend` scroll
  path is `app_path()`), so cross-file verdicts (V2-REFINE/V4/V8) need a full-index
  validation script or the consumer's own `judge` to exercise them.
- `composer readme` after touching the prophet roster (the table is autogenerated;
  `ReadmeIsCurrentTest` enforces it).
