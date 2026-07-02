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

6. **Finish:** once the end gate is green, run `commandments plan done`. This ends the plan and clears the keep-going Stop nudge.

## Autonomy

Grind through the phases without stopping for input. When keep-going is enabled, the Stop hook re-nudges you to continue until you run `commandments plan done` — so only stop when you **genuinely need user input** or are truly blocked (and then say why). Lint, type-checks, and any other gate are **not universal**: they run only if the project declared them in `planExecution()->onComplete(...)` (or you were explicitly asked), never assumed.

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
    ->onComplete('composer test'));// the end gate; judge --branch runs after
```

On `composer update` a starter block is injected automatically, its `onComplete` inferred from the project's own composer/npm scripts. Edit it freely.
