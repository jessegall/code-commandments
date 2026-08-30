# orchestration

Making the orchestrator mode real, and proving it by running this project's own
development under it.

**Status: live.** The board, receipts, refusals, profiles and the stop gate are
shipped and in use here. What remains is below, as sidequests.

## The rulings this plan is built on

Each was decided once, with a reason. The reason is the half that lets a later
reader see whether the premise still holds.

**A fact does not have to be wrong when written to be wrong when used.** The rule
everything else serves. A durable document quotes a COMMAND, never a number.

**A report is a claim; a receipt is what a tool read.** `--ran` exists so the number
filed is one a process returned.

**A refusal that exits 0 is not a refusal.** A worker chains its work behind
`claim && …`, so the exit code is the whole mechanism.

**A refusal whose cost is paid in the resource that has run out is an accelerant.**
Why the compaction gate never blocks, and why a nudge fires during a wait.

**`Stop` is the only hook an agent can avoid indefinitely.** It needs a turn to END.
Anything that must reach a working agent rides `PostToolUse`.

**Only the holder can say a hold is finished.** Accept after the worker's own report;
`orphan` only when it is demonstrably unreachable — and with liveness honestly
absent, "demonstrably" means pinged and silent, not merely quiet.

**A tool must never state a fact it did not measure.** Where it cannot measure, it
says so — `COULD NOT MEASURE`, `merge-base not asked`, "nothing here reaches an
agent, so none of it says one is still alive".

## Shipped

- v4.283.0 — the compaction gate instructs and never blocks
- v4.284.0 — refusals exit non-zero; `assistant` checks its target; the merge-base
  tells its two absences apart; `sync` keeps the project's own gitignore lines
- v4.285.0 — the stop gate reaches a working agent; `[!update]`; `orchestrate
  --write`; `assign` replaces its binding
- v4.286.0 — `routine.md`, a profile's standing habits, nudged at every stop
