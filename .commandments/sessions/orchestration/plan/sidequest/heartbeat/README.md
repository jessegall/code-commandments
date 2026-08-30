# heartbeat

**Jesse's design.** A default heartbeat ships with the package and is copied into a
new profile at setup, beside `behaviour`/`restrictions`/`traps`/`lane.sh`.

`heartbeat.md` is to the loop what `lane.sh` is to the lane: the package provides the
contract and the traps already paid for, and the project edits the content in a diff.

## THE RULE, at the top of the generated file

**A loop may state no fact it did not compute at fire time.**

Paid for: a heartbeat asserted a ratchet of 138 at every firing. It was 106, then 98,
then 91, then 78 — wrong by 60, all day. **A stale number in a system-voiced message
does not read as missing, it reads as authoritative.**

Anything numeric, stateful or countable is computed at fire time or omitted. Anything
static is a rule, a role or a pointer. Where computing is expensive, omit and point:
*"ratchet: see `gates.sh`"* costs nothing and lies never.

**Interpolate `build` commands, never hand-written status.** Everything a heartbeat
wants — the board, reported versus idle, `lane list`, the roles — is already computed
by a command whose output is current by construction. That is the difference between
a correct heartbeat and one that quotes a number somebody typed once.

## Two clocks

- **Role — periodic and rare.** It defends against drift and compaction, and drift is
  slow. Hours, not minutes.
- **State — event, never periodic.** Periodic firing never once caught a state
  problem: the harness had already reported the moment an agent finished. A nudge
  that fires with nothing new teaches skimming.

## What it carries, in order

`reported` workers first and loudest — a person is waiting. Then blocked questions
with their addressee, version drift, and an open lease only when it is older than the
round it belongs to. **Plus the identifiers that die with the session but not with
the build** — live agent ids, lane paths, port offsets, the sha last handed out. That
is the one thing a heartbeat has told an orchestrator that it could not have
recovered after a compaction.

Left out: round counts, receipts, anything computable in one command when needed, and
standing rulings — restating an unbroken rule trains the reader to skim the block
that also holds the one that has been broken.

## Never

Send the orchestrator to read code, a diff or a transcript. Ask for a re-summary of
progress. Tell it to stop and ask the user where the project said to work
autonomously. Run uncapped — a nudge becomes a status essay.

Delivered between turns, never mid-turn.

## Delivery

Copied into a new profile by `orchestrate profile <name>`, beside
`behaviour`/`restrictions`/`traps`/`lane.sh` — the package provides the contract and
the traps already paid for; the project edits the content in a diff.
