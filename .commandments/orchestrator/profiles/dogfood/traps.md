# traps

<!-- failures already paid for, each with what it cost -->

- (2026-08-29, cdf1247b) A refusal that exits 0 is not a refusal — a worker chaining `claim && work` proceeded after being refused, restoring the exact collision the hold prevents. Cost: would have collided on the first dispatch.

- (2026-08-29, cdf1247b) Blocking a compaction does not defer it — the harness carries on uncompacted until the hard wall, which ends the turn outright. Cost: a session stopped mid-tool-call.

- (2026-08-29, cdf1247b) `Stop` is the only hook an agent can avoid indefinitely; it needs a turn to END. A dozen sleep-polls meant a 12-condition gate held nothing for an hour. Cost: every parked task invisible for that hour.

- (2026-08-29, cdf1247b) A tool that names a cause it did not measure: the gate blamed settings.json for a Stop hook that was correctly wired. Cost: would have sent the next reader to fix a file that was fine.

- (2026-08-29, cdf1247b) With one hand on both sides of the record, a stale board stays perfectly self-consistent — there is no second party to disagree with you. Watch for skipping a `report` because you already know what it would say; that is the tell.

- (2026-08-29, cdf1247b) The board tracks ITEM ownership, not FILE ownership — two workers on different items can collide in the same file and nothing warns. Reason about the files before dispatching, because the board will not.

- (2026-08-29, cdf1247b) A subagent's writes are attributed to the ORCHESTRATOR's session: the WriteGate demanded I declare work I did not do, and the sin-check told me to fix a file a live worker was holding. Obeying either would collide with the worker. Cost: would have corrupted a lane's work in progress.

- (2026-08-29, cdf1247b) Workers writing into the SHARED checkout block incremental release: a live worker's half-landed code sits in the tree, so the full gate cannot go green and the integrator cannot ship a finished item independently. This is the argument for lanes (worktrees) that we deferred — the cost only appears once workers actually write.

- (2026-08-29, cdf1247b) I asserted 'the board is on disk and always was' as durability. It is session-scoped and untracked — the same bug as .roles, which profiles already fixed one level up. A peer MEASURED it and I had not. Cost: a design ruling made on a false premise.

- (2026-08-29, cdf1247b) A worker measured ANOTHER worker's in-progress tree and reported the failure as current; the methods had already been removed. In a shared checkout a gate result is a claim about two workers' code at the instant it ran, so a red from a neighbour's file must be re-measured before it is believed.

- (2026-08-29, cdf1247b) I accepted an item while its worker was still measuring, so its later receipt was refused with 'Nobody holds it' — true, but it reads as a mistake by the worker rather than a settled item. Numbers it had measured could not reach the record. Accept when the WORKER is done, not when my own check goes green.
