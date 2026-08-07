---
name: commandments-executing-plans
description: "How to EXECUTE an approved plan — branch, work phase by phase, commit and check as you go, run the full gate once at the end, and grind to completion without stopping. Read this the moment a plan is approved / you exit plan mode, BEFORE writing any code. The plan-reminder hook loads it for you and injects this project's concrete profile (branch prefix, base, push cadence, the `commandments checks` commands, keep-going policy)."
---

# Executing plans

> A plan is approved by *planning*. It is finished by *disciplined execution*: branch first, work one phase at a time, keep the checks cheap in-between and exhaustive at the end, and don't stop until it's done.

## The principle

Once a plan is approved, the failure modes are always the same: working on the base branch, running the slow full gate between every phase, committing erratically, and stopping half-way for input that isn't actually needed. This discipline removes all four. You judge and run the heavy gate **once, at the end** — that is when `--branch` gives the whole-plan picture anyway — and you keep momentum through the phases.

The **plan-reminder hook** injects this project's concrete profile when the plan is approved (branch prefix, base branch, push cadence, the exact `commandments checks` commands, and whether keep-going is on). Follow that profile; the steps below are the shape.

## The steps

1. **Branch first.** If you're on the base branch (`main`/whatever the profile names), cut a new branch for the plan (the profile gives the prefix, e.g. `plan/<slug>`). Never grind a plan on the base branch.

2. **Write the phases down** as a todo list — one item per phase — so progress is visible and nothing is dropped.

3. **Run the start checks once:** `commandments checks start` (environment setup the plan needs — a no-op if the project declared none).

4. **Work phase by phase.** For each phase:
   - Implement it.
   - Run **only the tests that matter for this phase** — the new tests plus any plausibly affected — not the whole suite. Then run `commandments checks phase` (the project's fast between-phase checks).
   - **Commit** the phase. Push only if the profile says push-each-phase; otherwise push once at the end.
   - If working-state tracking is on (see below), **refresh `.commandments/.plan-working-state`** now — and again after any important event mid-phase.
   - Do **NOT** run the full suite or `commandments judge` between phases.

5. **At the very end, once every phase is done:** run `commandments checks complete`. It runs the project's full gate (test suite, lint, static analysis — whatever it declared) and **always appends `judge --branch`**. Fix every finding **at its source** (never launder a sin with a default/cast/null-check), re-run, and repeat until it is completely clean.

6. **Verify the constraints** (if the plan has any — see below). Run `commandments constraints check`, review your **whole branch diff** against each one, fix any violation at its source, then `commandments constraints verified`.

   **Testing methodology:** right after approval (with the constraints question), also ask the user — via AskUserQuestion — **how tests are handled** for this run, and record it: `commandments testing set "<methodology>"`. Offer the standard methods (write+run tests each phase / all tests at the very end / only add new tests / only fix broken tests / custom), plus, when the project configured a default `testFlow`, a "use the project's test flow" option. Hold to the recorded methodology through the phases — a reminder re-surfaces it; `commandments testing show` prints it. It's a working style, not a diff-verified gate, so it doesn't block `plan done`.

7. **Finish:** once the end gate is green and constraints are verified, run `commandments plan done`. This ends the plan and clears the keep-going Stop nudge. (It **refuses** while constraints are unverified.)

## Constraints

A **constraint** is a natural-language architectural invariant `judge` can't decide — e.g. *"the frontend is presentation-only; all logic and lookups live in the backend."* Some are **global** (declared in config, every run); you also gather **local** ones per run.

- **At the start** (right after approval), ask the user — via AskUserQuestion — whether this run has any constraints, and record each: `commandments constraints add "<rule>"`. The project's global constraints are already in force; `commandments constraints list` shows them all.
- **As you work**, hold to them. A reminder re-surfaces them periodically. If the project set `enforceConstraintsEachPhase()`, run `commandments constraints check` each phase too.
- **At completion** (step 6), the check is a **hard gate**: `commandments constraints check` prints each constraint and tells you to review the **entire branch diff vs the base** (`git diff <base>...HEAD` — everything you created across all phases, not just the last change). For each: confirm compliance, or **fix the violation at its source and commit**. Only when all hold do you run `commandments constraints verified` — and only then will `plan done` proceed. If you commit anything after verifying, it goes stale; re-verify.

## Autonomy

How hard you push is set by the project's **mode** (`planExecution()->mode(...)`; `commandments plan status` shows it). The Stop hook enforces it — grind through the phases; when the mode keeps you going, a stop re-nudges you to continue until the plan is finished.

- **`Supervised`** — grind on your own, but a human stop is respected (you're nudged at most once).
- **`Autonomous`** — grind to the finish; a stop re-nudges until you're done. If you're **genuinely blocked** and need the user, run `commandments plan stuck`, then stop — it pauses the nudge for that one stop (so you aren't looped) while keeping the plan active; say what you're blocked on. One-shot: keep-going resumes the moment you continue.
- **`BestEffort`** — finish **as much of the plan as possible**, never asking. Don't wait for the user; decide yourself. If a step is genuinely blocked, **skip it and record it as DEFERRED** in your working state (with what's blocking it), then keep going. At the **end**, come **back and retry every deferred step** now that the rest is in place; only `plan done` once you've attempted them all. `plan stuck` refuses (it defers instead).
- **`Relentless`** — **never stop**. Do not ask the user and do not wait. When you hit a decision, choose the best option yourself and proceed. If a phase is genuinely blocked or not worth doing, **skip it** — note why in your working state and move to the next phase (no end-of-run retry pass). There is **no `plan stuck`** here (it refuses); the only exit is `plan done`.

A plan run ends the nudging exactly one way that counts as success:

- **Complete** → `commandments plan done`. Only when every reachable phase is done and the end gate is clean. This ends the plan. You may **not** `plan done` a plan that isn't complete.

Lint, type-checks, and any other gate are **not universal**: they run only if the project declared them in `planExecution()->onComplete(...)` (or you were explicitly asked), never assumed.

## Working state

When the project sets `trackWorkingState()`, keep a **living working-state record** at
`.commandments/.plan-working-state` — the one thing that survives a context **compaction**. The plan
(on disk) and the code (in git) already survive; what's lost is what lived only in the conversation. So
the record captures **ONLY what `git log` + the plan can't reconstruct**:

- a **Done / Doing / Next** cursor (finer-grained than commits),
- the **decisions** you made — and the alternative you rejected, and *why*,
- **plan changes agreed in conversation** (the plan file is the design; note where reality diverged),
- **gotchas** you hit the hard way, and the **exact next physical step**.

Refresh it **after each phase** and **after each important event** (a decision, a plan change we discuss).
Don't restate the plan — that's noise. Compaction gives no warning you can act on, so the record must be
current *before* it strikes — a heartbeat nudges a refresh as you work, and the record is **auto
re-injected on compact/resume**, so a compacted you resumes with the full picture.
It's cleared on `plan done` and on a genuinely-new session, and survives `compact`/`resume` (that's the point).

## The commands

<!-- BEGIN: commands:plan,checks,constraints,testing (auto-generated, run `composer sins`) -->
| Command | Does |
|---|---|
| `commandments plan status` | is a plan active (and stuck)? the resolved profile and mode (the default) |
| `commandments plan done` | end the plan — clears the marker so the keep-going nudge stops (run it once the end gate is clean) |
| `commandments plan stuck` | signal you are BLOCKED and need the user — pauses the nudge but keeps the plan active |
| `commandments checks [start\|phase\|complete]` | run that moment's checks in order, stopping at the first failure (default: complete) |
| `commandments checks <moment> --list` | print the commands that moment would run, without running them |
| `commandments constraints list` | the invariants in force for this plan (the default) |
| `commandments constraints add "<rule>"` | add one for this plan only, alongside the project's declared ones |
| `commandments constraints check` | print them with the whole-branch introspection instruction |
| `commandments constraints verified` | stamp them verified — this is what unblocks the `plan done` gate |
| `commandments testing show` | the methodology in force (the default) |
| `commandments testing set "<methodology>"` | record the one the user chose |

<!-- END: commands:plan,checks,constraints,testing -->

## Configuration

The profile lives in `.commandments/config.php`:

```php
$config->planExecution(fn ($plan) => $plan
    ->branchFrom('main')          // base to cut from + judge --branch base
    ->branchPrefix('plan/')       // the plan branch prefix
    ->pushEachPhase()             // push after every phase (default: once at the end)
    ->mode(PlanMode::Autonomous)  // Supervised | Autonomous | BestEffort | Relentless (never stop, skip blockers)
    ->onStart('composer install') // once, before the first phase
    ->eachPhase('composer lint')  // after each phase — keep it fast
    ->onComplete('composer test') // the end gate; judge --branch runs after
    ->constraint('The frontend is presentation-only; all logic lives in the backend.')
    ->enforceConstraintsEachPhase() // optional — else phase is a nudge, completion always the gate
    ->testFlow('Write and run the tests for each phase before committing it.') // default test methodology, offered at approval
    ->trackWorkingState()); // keep a living working-state record that survives context compaction
```

On `composer update` a starter block is injected automatically, its `onComplete` inferred from the project's own composer/npm scripts. Edit it freely.
