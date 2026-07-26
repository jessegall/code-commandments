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
| "add it to the to-do list" | the gate **and** a TodoWrite item — see below |
| "don't forget to X" / "remind me to X" / "later" | same: gate **and** tracker |

Write the condition **as a checkable statement**, not as a task: "the full test suite passes", not
"run the tests". You will be asked to verify it later, so it has to be something you can *check*.
One condition per call — stacking them keeps each one independently verifiable.

**Mirror it into your to-do list.** As soon as you record a condition, add the same statement to your
to-do list (TodoWrite) as a pending item, so the user can see at a glance what is holding you. The
gate's marker file is invisible to them; the to-do list is not. Keep the two in sync: when you strike
a condition off with `until met <n>`, mark its to-do item completed in the same breath.

### A to-do item is NOT a gate

The two are not interchangeable, and only one of them survives you:

| | To-do list (TodoWrite) | The gate (`commandments until`) |
|---|---|---|
| Who sees it | the user, live | the Stop hook |
| Holds a stop | never | every stop, until verified |
| Lives past this session | no | yes — it's a file |

So when the user defers something — **"add it to the to-do list"**, "don't forget to…", "remind me
to…", "later", "when you're done" — that phrasing is a DEFERRAL, and it takes **both**: the gate,
which is what actually brings the task back, and the tracker item, which is what shows them it
exists. Doing only the tracker satisfies the letter of "add it to the to-do list" and loses the task
the moment the session ends — which is exactly the failure this rule exists to stop.

Read it the other way too: nothing about the word "to-do" excuses you from the gate. If the user is
handing you work to do later, it is a condition.

Do **not** set a gate on your own initiative. It is the user's instrument; setting one for yourself
just to stay busy is out of bounds.

## Parking what the user says mid-work

The other half of the gate: when you are already working and the user speaks, their message is
either **steering** or a **separate task**. Decide which before you act — a `UserPromptSubmit` hook
puts this triage in front of you while work is in flight.

- **Steering the work in hand** — a correction, a change of approach, "while you're in there, rename
  that too", anything about the phase you are on. → **Do it now.** Parking it is a way of not doing
  it, and leaves the work wrong in the meantime.
- **A separate task**, one they explicitly deferred ("later", "when you're done", "after this", "add
  it to the to-do list", "don't forget to…"), or anything that would derail the phase you're in. →
  **Park it**, which is BOTH halves: `vendor/bin/commandments until "<the task, as a statement you
  can verify>"` **and** the same statement in your to-do list. Then carry straight on with what you
  were doing. The gate holds your stop at the end, so the task cannot be lost.
- **Unsure?** Cheap and inside the current phase → do it. Opens a new front → park it. The
  tie-breaker: would doing it now change what this phase is about?

Park it as something **checkable** — "the changelog has an entry for this release", not "look at the
changelog" — because you must verify it before you may stop. The to-do item is the visible half, never
the whole of it: a tracker entry with no gate behind it is a task you have agreed to lose.

## A plan takes precedence

While a plan is active the gate is silent: the plan's own keep-going hook owns the stop, and parked
conditions don't burn their release cap during a long grind. They take over the moment
`commandments plan done` clears the plan — which is exactly "at the end". `plan done` lists what is
now holding you, so the handover is never a surprise.

## Working under a gate

When you try to stop, the hook sends you back with the standing conditions. Then:

1. **Verify, don't assume.** Actually run the command, read the file, check the output. "I wrote the
   tests so they must pass" is not verification — a gate exists precisely because the user does not
   want that assumption.
2. **Condition holds?** `vendor/bin/commandments until met <n>` — the number the gate printed (see
   `until list`). Numbers are STABLE ids: striking one condition off never renumbers the rest, so
   you may read the list once and run several `met` calls off it safely. The gate lifts when the
   last one is struck off. Mark the matching to-do item completed at the same time, so the visible
   list tracks the gate.
3. **Doesn't hold?** Keep working. That is the whole point of the gate.
4. **Genuinely blocked** — you need a decision, a credential, something you cannot get?
   `vendor/bin/commandments until stuck`, then tell the user exactly which condition you cannot meet
   and why. That releases ONE stop while keeping the condition in force; the gate holds again the
   moment you continue.

**Never** run `until clear` to escape a condition you simply haven't met. `clear` drops the user's
gate entirely and is theirs to ask for ("forget that condition"). Marking a condition `met` when it
does not hold is the same offence: it reports success that isn't there. The same goes for
`until pause` — it is THE USER's switch for doing something else in between (it sets the whole gate
aside, conditions intact, and silences the nudges until `until resume`). Run it only when they ask;
reaching for it yourself is escaping the gate by another name.

Loop-safe by design: after 10 consecutive held stops with no condition met, the gate releases itself
and tells you to report back. Meeting a condition resets that count — real progress is never punished.

## The commands

| Command | Does |
|---|---|
| `commandments until "<condition>"` | set a condition (prints its number) |
| `commandments until list` | show every condition still standing |
| `commandments until met <n>` | strike condition `<n>` off as verified |
| `commandments until stuck` | blocked — release ONE stop, keep the condition |
| `commandments until pause` / `resume` | the USER's switch — set the whole gate aside (conditions kept, nothing holds, no nudges) and put it back |
| `commandments until clear` | drop every condition (only when the user asks) |
