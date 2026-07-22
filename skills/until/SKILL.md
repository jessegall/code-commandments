---
name: commandments-until
description: How to use `commandments until` — the stop gate the user sets when they say "keep going until X", "don't stop until the tests pass", "work until the build is green". Read this when the user asks you to set an until/stop condition, when you invoke /until, or when a Stop-gate hook message sends you back to verify a condition (`until met`, `until stuck`, `until clear`).
---

# The `until` stop gate

> The user says "keep going until the suite is green." That sentence is not a wish — it is a
> **gate**. Record it, and you cannot stop until you have *verified* it holds.

## What it is

`vendor/bin/commandments until "<condition>"` records a condition in the current session. While any
condition stands, a `Stop` hook holds every stop you attempt and sends you back in with the
condition text, telling you to verify it. The gate lifts only when you strike the conditions off.

It is the plan-free sibling of the keep-going plan nudge: **no plan has to be active**, and there is
no config opt-in — it exists only because the user asked for it, right now, in this session. It is
scoped to this session and this worktree, so it never holds another session's stop.

## When to set one

Set a condition the moment the user expresses one, in their own words:

| The user says | You run |
|---|---|
| "keep going until the tests pass" | `vendor/bin/commandments until "the full test suite passes"` |
| "don't stop until the build is green and the README is updated" | two calls — one condition each |
| "/until the linter is clean" | `vendor/bin/commandments until "the linter is clean"` |

Write the condition **as a checkable statement**, not as a task: "the full test suite passes", not
"run the tests". You will be asked to verify it later, so it has to be something you can *check*.
One condition per call — stacking them keeps each one independently verifiable.

Do **not** set a gate on your own initiative. It is the user's instrument; setting one for yourself
just to stay busy is out of bounds.

## Working under a gate

When you try to stop, the hook sends you back with the standing conditions. Then:

1. **Verify, don't assume.** Actually run the command, read the file, check the output. "I wrote the
   tests so they must pass" is not verification — a gate exists precisely because the user does not
   want that assumption.
2. **Condition holds?** `vendor/bin/commandments until met <n>` — the number the gate printed (see
   `until list`). The gate lifts when the last one is struck off.
3. **Doesn't hold?** Keep working. That is the whole point of the gate.
4. **Genuinely blocked** — you need a decision, a credential, something you cannot get?
   `vendor/bin/commandments until stuck`, then tell the user exactly which condition you cannot meet
   and why. That releases ONE stop while keeping the condition in force; the gate holds again the
   moment you continue.

**Never** run `until clear` to escape a condition you simply haven't met. `clear` drops the user's
gate entirely and is theirs to ask for ("forget that condition"). Marking a condition `met` when it
does not hold is the same offence: it reports success that isn't there.

Loop-safe by design: after 10 consecutive held stops with no condition met, the gate releases itself
and tells you to report back. Meeting a condition resets that count — real progress is never punished.

## The commands

| Command | Does |
|---|---|
| `commandments until "<condition>"` | set a condition (prints its number) |
| `commandments until list` | show every condition still standing |
| `commandments until met <n>` | strike condition `<n>` off as verified |
| `commandments until stuck` | blocked — release ONE stop, keep the condition |
| `commandments until clear` | drop every condition (only when the user asks) |
