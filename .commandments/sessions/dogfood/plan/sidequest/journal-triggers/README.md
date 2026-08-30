# journal-triggers

**Jesse's design.** The journal records faithfully and is nearly empty, because
almost nothing gets tagged. An orchestrator deciding constantly for five hours
produced three tags against forty-nine hand-written pins.

## The cause

The nudge fires on `PostToolUse` after a write. An orchestrator barely writes files
— its work is decisions, which live in its messages and its briefs. **The nudge
fires where the work is, not where the decisions are.** For a builder those coincide;
for an orchestrator they are nearly disjoint.

## What to build

**Trigger on a COUNTER of tool calls**, not on a file write. A counter is blind to
role and therefore correct for all of them; it survives a quiet stretch of reading
and dispatching, which is exactly when the most rulings are made.

- X configurable per profile, defaulting to 10–15.
- **It says the count**: "3 tags in 40 tool calls" is a fact to act on; "remember to
  tag" is wallpaper.
- **Reset on a tag or a `remember`, never on the nudge** — otherwise it is a
  metronome. Tagging silences it for another X: a debt that clears when paid.
- **Nudge for `remember` too.** The pins carried the build; a counter that asks only
  for tags optimises for the cheap thing that did not save the session.
- **One line.** A nudge every X calls spends context every X calls.

**Also trigger on WORK VOLUME** — uncommitted diff across the checkout AND its lanes,
since on an orchestrated build the work happens in worktrees while the root sits
clean. Volume measures the build's activity where a counter measures the agent's.

**And on a commit landing with a `[!start]` still open.** The sharpest of the three:
a commit is the one moment where something is definitively finished and the record
definitively is not. A commit message says what changed; the update says why it was
decided that way and what it cost to find out.

**Both guarded by "last update older than N"**, which is what keeps it a milestone
rather than a metronome. The rule is an AND, not an OR.

## Forcing, not just asking

If nothing has been tagged for X, **force an `[!update]`** — cheap to satisfy,
expensive to ignore. One short entry clears it.

- **Pre-fill from the board.** What is in flight and who holds it is already
  computed; leave the agent only the reasoning, which is the half no tool can
  compute and the half that matters.
- **Read the STAGE, not the count.** A worker `blocked` on a ruling must not be told
  to keep working — the board already draws that line.
- **Not clearable by noise.** A gate cleared by "still working" trains you to say
  nothing. Whatever check is available — that it names a live item, that it differs
  from the last one — is worth more than the forcing itself.

**`journal remember` stays voluntary.** It is the pin, it is deliberate, and that is
why forty-nine of them carried a build.

## Also open, from the same root

**The nudge must reach an orchestrator's DECISIONS**, which live in its messages and
its briefs rather than in file writes. `MessageDisplay` already sees assistant
messages, so the reach exists; the trigger is what does not.

**`[!update]` can name a board item**, so it joins that item's record instead of
floating as a separate note — and `doctor` can then say "3 of 5 open", which is the
first time the board would know how far along something actually is.
