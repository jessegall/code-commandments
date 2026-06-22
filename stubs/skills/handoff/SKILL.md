---
name: commandments-handoff
description: How to produce a HANDOFF.md so a FRESH context (new session, teammate, or a revived plan after a stall/compaction) resumes cold with zero archaeology. Run `sh .claude/hooks/handoff.sh` to auto-fill the snapshot, then complete the narrative. Read before stopping mid-task, before a long/compacting context, or when handing work off.
---

# Handoff — leave a cold-resume document

## Purpose

A handoff is a self-contained snapshot of where the work stands so whoever picks
it up next — a new session, a teammate, or *you* after the context window
compacts — can continue with no archaeology. It's the one-shot, comprehensive
cousin of a running progress note.

## When to use this skill

Two kinds of handoff, with OPPOSITE effects on an active plan loop:

**Checkpoint handoffs — the loop stays ARMED, keep going:**
- The context is large or about to compact, and the durable state should live in a
  file, not a fading window.
- You're driving an autonomous plan and want a recoverable checkpoint mid-run.

Writing a checkpoint handoff is insurance — it does **NOT** release the plan loop
and is **not** a reason to stop. Write it, then continue; `plan-release.sh` will
refuse a checkpoint/compaction reason.

**Terminal handoffs — you are stopping, release the loop:**
- You hit a GENUINE blocker (a decision only the user can make, info you can't
  find/infer, an unrecoverable failure) and are handing back to the user.
- The work is genuinely done.

For a terminal handoff, release the loop explicitly: `sh .claude/hooks/plan-release.sh "<reason>"`.

## How

Run the packaged helper (it ships in `.claude/hooks/`):

```
sh .claude/hooks/handoff.sh
```

It writes `HANDOFF.md` at the repo root with the **mechanical snapshot
auto-filled** — branch + upstream, `git status`, uncommitted diff stat, recent
commits, the commandments snapshot (`judge --git`), the active-plan marker, AND
the plan-progress memory included verbatim. Then **you complete every
`>>> TODO <<<` section** with the narrative:

1. **Goal** — what this work delivers (1–2 lines).
2. **State** — done (with commit shas) · in progress · remaining (ordered).
3. **Next step** — the exact next action on resume.
4. **Decisions & deferrals** — choices made, anything deferred (with why).
5. **Resume notes** — key files, gotchas, how to verify (tests / gate).

`HANDOFF.md` is gitignored (transient working state). Keep it accurate and
overwrite it as the work moves.

## What to read when

| Read | When |
|---|---|
| `reference/what-makes-a-good-handoff.md` | You want the bar for each section — what "good" looks like vs a useless stub. |
