# worker-journals

A subagent's journal is never written — the recorder is silenced in subagents, so everything a worker decided dies with its transcript

## Measured, not assumed

`JournalRecorder` does not override `speaksToSubagents()`, so `Hook::handle` silences
it inside any spawned agent — it never runs there. And a subagent's payload carries
the PARENT's `session_id`, so even if it ran, its entries would land in the
orchestrator's own journal indistinguishable from the orchestrator's.

This session: **728 agent entries, all the orchestrator's.** Three workers ran ~180
tool calls between them and left two traces — both inside orchestrator messages
relaying their reports by hand.

## Why it matters

**A worker's final report is the single point of failure for its entire session.**
Everything it discovered, every correction it made mid-flight, every `[!start]` it was
briefed to write — gone when the transcript goes. Tonight's workers were good, so the
reports were good. A thin report would have taken everything with it, invisibly.

## The fork

**Record them, in a lane keyed by agent id inside the parent's journal.** One store,
provenance kept, `journal --agent=<id>` readable afterwards. The cost is volume.

**Or leave it silent** and let the report carry the weight — which is today's
behaviour by accident rather than by decision, and is exactly the load the secretary
exists to take off a report.

## RULED (Jesse): an assistant gets its OWN journal

Both options above were wrong. Not "record into the parent's journal" and not "leave
it to the report" — **a worker keeps its own.**

**The word that decides it is PERSISTENT.** A one-shot worker's journal is only ever
useful to somebody else. But an agent kept alive and resumed across dispatches has the
same problem the orchestrator has — its context is compacted, and what it decided is
lost while what it did survives. **A journal does for it precisely what it does for
the orchestrator**, and that is the whole reason the mechanism exists. It was built
for one participant in a system designed around several.

## Which also settles the identity question

The payload carries the parent's `session_id`, which is why writing to "the session's
journal" would mix a worker's entries into the orchestrator's indistinguishably. But
it ALSO carries `agent_id` — so a worker's journal keys on the AGENT.

```
sessions/<name>/agents/<agent-id>/.journal
```

Under the session because that is where the run lives; keyed by agent because that is
whose record it is. A worker running `commandments journal` resolves its own agent id
and reads its own back — the same command, answering about the caller.

## What that needs

`JournalRecorder` must speak inside subagents, which today it does not — but writing
to the AGENT's journal rather than the session's, or it recreates the mixing problem
with extra steps.

**And the orchestrator reading a worker's journal becomes a deliberate act** rather
than an accident of storage: `journal --agent=<id>`. Which is the right shape — a
worker's reasoning is available when somebody goes looking, not spilled into the
orchestrator's own record where it would drown the thing it came to find.
