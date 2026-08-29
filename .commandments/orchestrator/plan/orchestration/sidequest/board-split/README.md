# board-split

**The board takes the profile split**, and this is the piece everything else hangs
from.

## The defect, measured

The board is session-scoped and untracked — `.commandments/sessions/<id>/.board`,
caught by the `*` rule. Verified 2026-08-30: **0 tracked boards.** It outlives the
worker and dies with the session.

That is the `.roles` bug, which profiles already fixed one level up: session-scoped
state silently stopped a refusal from refusing. The fix was generalised into a
principle and never applied to the thing sitting next to it.

## The split

- **The ITEM is durable** — its title, body, completion command, parent, stage. In
  git, in a diff, reviewable, alive tomorrow, readable from a fresh session. This is
  the folder of `plan-tree`: the item IS the folder, and the path IS the parent.
- **The HOLD is the instance** — who holds it right now, since when, which round.
  Session-scoped, because a hold by an agent id is meaningless tomorrow and
  presenting a dead one as live is worse than losing it.

## What falls out for free

**`orphan` becomes cheap and obvious.** Today it must be deliberate because losing
the hold would lose the work. Split them and it is simply what happens when a
session ends: the hold dies, the item stays.

**A worker's queue is the durable item list filtered by holder** — no second store,
no `item:` frontmatter tying two records together.

**"3 of 5 open" is one query**, so the board can finally say how far along something
actually is.

## Completion criteria are RUN, not read

Taken whole from the build that proved it: of five items given to one builder, three
were fixed, one **did not reproduce**, and one was fixed by a different mechanism
than the brief described. A prose checkbox would have shown five ticks.

So an item stores a completion command, and closing it RUNS that command — the
`--ran` machinery pointed at the smallest unit of work there is. Where a criterion
genuinely cannot be a command, it is filed as `asserted` and **says so**, which is
the three-verdict shape one level down.
