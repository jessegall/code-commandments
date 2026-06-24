---
name: commandments-profile
description: Switch the code-commandments work profile (disabled / grind / phased / sins-only / penance / repentr) for this project. Use when the user says "/commandments-profile [name]", "set the commandments profile to …", "switch to grind/phased/disabled/penance/repentr", or asks to make code-commandments quieter/stricter, to turn it off, or to repent a single prophet. Running the switch re-wires the git + Claude hooks and re-briefs you on the new contract.
---

# code-commandments — switch work profile

## Purpose

The **profile** is the top-level switch for how intrusive code-commandments is.
Each profile installs its own set of git hooks, Claude hooks, briefing, and
CLAUDE.md section, and tears down the previous one. This skill switches it for the
user and adopts the new contract immediately.

## The profiles

| Profile | What it does |
|---|---|
| `disabled` | Dormant. No hooks, no gate, no briefing, no CLAUDE.md section — you become unaware the package judges. The default. |
| `grind` | Heads-down. **No judge/tests between phases** — implement the whole plan, then reckon (run `judge` + your tests) once at the end. A pre-push gate blocks unresolved **sins** across the branch; warnings are flagged but don't block. |
| `phased` | Face-by-face. Pre-commit gate blocks staged sins **and** warnings; per-phase nudges drive fix-as-you-go; full briefing. |
| `sins-only` | Like `phased` but warnings are silenced everywhere — only sins surface and gate. |
| `penance` | Cleanup mode — drive the existing backlog of sins+warnings to zero. **No commit gate** (commit progress freely; fixing a file never re-arms a blocker on it); a pre-push gate blocks pushing while sins remain. START with `pilgrimage` (the forward-only walk, one prophet at a time) — do NOT bulk-`repent` or `judge` first; both are locked while the walk runs. On an [AUTO-FIXABLE] prophet, run `autofix` to fix THAT prophet in place, then `next`. Read each output IN FULL. Keep-going holds the session open until `judge` is righteous. Never skip a messy file — that's the job. |
| `repentr` | Single-prophet repent — drive **one** prophet's findings to zero via a guided single-prophet **pilgrimage**, not the whole backlog. A bare `pilgrimage` REFUSES and lists candidate prophets ranked by finding count: present that menu and **ask the user WHICH PROPHET** (don't pick). Then `pilgrimage <NAME>` walks only that prophet, `autofix`/`next` advance it, `todo` lists what's left. No git gate, no keep-going loop; stay on the one prophet — do not touch others. |

## How to run it

When the user invokes `/commandments-profile [name]` (or asks to change the
profile):

1. **Switch it.** Run the profile command via this project's runner:
   - Standalone: `vendor/bin/commandments profile <name>`
   - Laravel: `php artisan commandments:profile <name>`

   With no name, show the current profile and the list:
   `commandments profile` (show) and `commandments profile list`.

2. **Adopt the new contract.** The command prints the now-active contract
   (judge scope, gate, cadence, whether warnings are flagged). **Read it and
   follow it from now on — discard any previous commandments contract.** If you
   switched to `grind`, that means: implement the entire plan phase by phase with
   NO judging or tests between phases, then run `judge` + the test suite once at
   the end before pushing. If `disabled`, code-commandments is now dormant — stop
   running its checks.

3. **Confirm** to the user what changed in one line (e.g. "Switched to **grind** —
   I'll implement the whole plan, then judge + test before pushing").

## Notes

- The selection is local and gitignored (`.commandments/profile`), so it's
  per-developer and never committed.
- You can switch any time, mid-session — the per-turn drift hook also re-briefs
  you if the profile changes by any other means.
- `grind`'s pre-push gate runs `judge --branch` (everything changed since the
  branch base, so it survives the intermediate phase commits).
