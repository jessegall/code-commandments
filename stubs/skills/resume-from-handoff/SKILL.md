---
name: commandments-resume-from-handoff
description: How to pick up work cold from a HANDOFF.md (or a *-progress plan memory) — read the whole doc, RE-VERIFY its snapshot against the live repo before trusting it, then continue from the Next step. Read at the START of a session that inherits a handoff, or when asked to "continue", "resume", or "pick up where we left off".
---

# Resume from a handoff — pick up cold, safely

## Purpose

The consumer twin of the `handoff` skill. A `HANDOFF.md` (written by
`.claude/hooks/handoff.sh`) is a cold-start snapshot of in-flight work. This skill
is how to turn that document back into momentum **without blindly trusting a
snapshot that may be stale** — the repo can have moved since it was written.

## When to use this skill

- A session starts and `HANDOFF.md` exists — **but ask first (see step 0); never
  auto-resume.**
- The user says "continue", "resume", "pick up where we left off", or points you
  at a handoff / a `*-progress` plan memory.
- A plan loop stalled and you're reviving it.

## How

0. **ASK FIRST — never auto-resume.** If you reached this because a fresh session
   found a `HANDOFF.md` (or a `*-progress` memory), do NOT start resuming on your
   own. Ask the user — "I see a handoff from a previous session — want me to resume
   from it?" — and proceed only on a yes, or when the user explicitly asked to
   resume. (This mirrors `handoff-detect.sh`, which offers but never auto-starts.)

Then run the packaged helper — it assembles the whole briefing in one command
(the read/verify counterpart of `handoff.sh`):

```
sh .claude/hooks/resume.sh
```

It prints, in order: the existing `HANDOFF.md`, a **live re-verification** of the
repo (current branch, `git status`, recent commits, the commandments gate), the
**plan-progress memory** (the authoritative plan, surfaced even if the loop is
off), and a NEXT STEPS checklist. Then:

1. **Read the WHOLE briefing** — every handoff section (Goal, State, Next step,
   Decisions, Resume notes) and the embedded plan-progress memory.

2. **RECONCILE it against the LIVE REPO section the script printed** — the handoff
   was true when written; the tree may have moved. If work already landed, the
   branch changed, or new commits exist, trust the **repo** — the handoff is a
   hint, the repo is the truth.

3. **Create an ACTIVE TODO LIST** (the TodoWrite tool) — one item per phase — and
   keep it live (mark each in_progress when you start it, completed when it lands)
   so the user can follow your progress in the terminal.

4. **Re-arm the plan loop** — only when the plan-progress memory still lists
   REMAINING phases AND `.claude/plan-active` is absent (if the marker exists the
   loop is already armed), AND `.claude/hooks/plan-start.sh` exists:
   `sh .claude/hooks/plan-start.sh`.

5. **Continue from the Next step** — the handoff's, or (if there is no HANDOFF.md)
   the `*-progress` memory's NEXT STEP. Keep durable state current as you go:
   refresh the `*-progress` memory each committed phase, and rewrite `HANDOFF.md`
   via `sh .claude/hooks/handoff.sh` before any pause/compaction — a handoff is
   checkpoint insurance and does **NOT** release an active plan loop; keep going
   unless the plan is DONE or you hit a genuine blocker. Pair with the `handoff`
   skill for the write side.

6. **When the work is fully done**, remove the handoff as part of the final
   commit: `git rm HANDOFF.md` (recoverable via history) — never bare-`rm` it, it
   is the richest resume source.

## What to read when

| Read | When |
|---|---|
| The repo's `HANDOFF.md` | First, **if present**. If there is no HANDOFF.md, resume from the `*-progress` plan memory instead (it has its own NEXT STEP). |
| The referenced `*-progress` memory | When the handoff names one, or when no HANDOFF.md exists — it is the authoritative, phase-by-phase plan. |
