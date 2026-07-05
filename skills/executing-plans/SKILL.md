---
name: commandments-executing-plans
description: How to EXECUTE an approved plan — branch, work phase by phase, commit and check as you go, run the full gate once at the end, and grind to completion without stopping. Read this the moment a plan is approved / you exit plan mode, BEFORE writing any code. The plan-reminder hook loads it for you and injects this project's concrete profile (branch prefix, base, push cadence, the `commandments checks` commands, keep-going policy).
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

Grind through the phases without stopping for input. When keep-going is enabled, the Stop hook re-nudges you to continue until you finish. Two ways a plan run ends the nudging:

- **Complete** → `commandments plan done`. Only when every phase is done and the end gate is clean. This ends the plan.
- **Blocked** → `commandments plan stuck`, then stop. When you genuinely need the user and cannot proceed — you may **not** `plan done` a plan that isn't complete. `plan stuck` pauses the keep-going nudge for that one stop (so you aren't looped back in while blocked) but keeps the plan **active**; say clearly what you're blocked on. It's one-shot: the moment you continue, keep-going resumes on its own — no need to un-stick it manually.

Lint, type-checks, and any other gate are **not universal**: they run only if the project declared them in `planExecution()->onComplete(...)` (or you were explicitly asked), never assumed.

## Configuration

The profile lives in `.commandments/config.php`:

```php
$config->planExecution(fn ($plan) => $plan
    ->branchFrom('main')          // base to cut from + judge --branch base
    ->branchPrefix('plan/')       // the plan branch prefix
    ->pushEachPhase()             // push after every phase (default: once at the end)
    ->keepGoing()                 // Stop hook re-nudges until `plan done`
    ->onStart('composer install') // once, before the first phase
    ->eachPhase('composer lint')  // after each phase — keep it fast
    ->onComplete('composer test') // the end gate; judge --branch runs after
    ->constraint('The frontend is presentation-only; all logic lives in the backend.')
    ->enforceConstraintsEachPhase() // optional — else phase is a nudge, completion always the gate
    ->testFlow('Write and run the tests for each phase before committing it.')); // default test methodology, offered at approval
```

On `composer update` a starter block is injected automatically, its `onComplete` inferred from the project's own composer/npm scripts. Edit it freely.
