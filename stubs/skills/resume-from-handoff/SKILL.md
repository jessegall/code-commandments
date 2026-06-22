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

- A session starts and `HANDOFF.md` exists at the repo root.
- The user says "continue", "resume", "pick up where we left off", or points you
  at a handoff / a `*-progress` plan memory.
- A plan loop stalled and you're reviving it.

## How

Run the packaged helper first — it assembles the whole briefing in one command
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

3. **Create an ACTIVE TODO LIST** (the TaskCreate tool) — one item per remaining
   phase — so the user can follow your progress in the terminal.

4. **Re-arm the plan loop** if a plan is unfinished and the loop is no longer
   armed: `sh .claude/hooks/plan-start.sh`.

5. **Continue from the Next step** — and keep durable state current as you go
   (refresh the `*-progress` memory each committed phase; rewrite `HANDOFF.md`
   via `sh .claude/hooks/handoff.sh` before you stop again). Pair with the
   `handoff` skill for the write side.

## What to read when

| Read | When |
|---|---|
| The repo's `HANDOFF.md` | Always, first — it is the snapshot you're resuming. |
| The referenced `*-progress` memory | When the handoff names one — it is the authoritative, phase-by-phase plan. |
