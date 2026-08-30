# compaction-recovery

**The first real evidence, and it is bad.** An orchestrator went through a genuine
compaction with all of this shipped, and reported what it measured rather than what
it remembered.

## 1. The recovery is never reached

**It ran NONE of the commands.** Not `--back=1`, not `user`, not `verify`, not
`pinned`. The hook fired, said *"BEFORE your next substantive step"*, and named all
three — and it went straight to `git status`, `composer update`, refreshing lanes,
dispatching.

**Its first `journal` call after compacting was `remember`.** Writing, not reading.

Three tags in five hours because it did not reach for the recorder; zero reads at the
one moment the record exists for, because it did not reach for the reader. **Two
mechanisms that work and are not reached is not two failures. It is one, and it is
about the reaching.**

## 2. The summary preserved an INFERENCE as a fact — and it cost a collision

The summary recorded, as settled: *"the stuck agent is the JSON builder the user
killed… it is a zombie."* That was a judgement formed from one `ListAgents` reading
and a five-minute-stale mtime. **The compaction froze the inference and handed it back
as a finding.**

Acting on it, the orchestrator claimed the item, wrote a fresh brief, and **dispatched
a second builder into the same lane, on the same eight files, while the first was
still writing them.** The notification saying it was alive arrived in the same tool
result as the spawn.

**The killer detail: the correction was already in the record.** Hours earlier the
user had said *"the agents did stop running — but their session was never lost."* The
same error, already made, already corrected, already written down — then made again
off its own summary.

**A pin preserves a fact. What did the damage was a pin-SHAPED thing that was never a
fact** — an inference with its provenance stripped, wearing the same confidence as
things that were checked.

> **A record that stores an inference without its provenance is worse than one that
> drops it.**

The summary would have been better having dropped that line entirely.

## 3. The pin channel does not deliver at scale — with a number

```
pin payload : 50,053 bytes · 550 lines · 55 facts
delivered   : a spilled file with a 2KB preview — about 4%, rest never opened
```

**The pins that survived did so by being REWRITTEN INTO the summary**, in its own
concepts section, not by being attached to it. Which says what a pin is actually for:
**reaching the SUMMARISER.** The large dump to the fresh session is the part that does
not pay, and 55 is already past the size where a payload arrives whole.

## 4. What to build, and it is their proposal

**Run the reads FOR the agent and put the OUTPUT in what it wakes to** — not commands
it is told to run at the moment it is least inclined to run anything.

> *"You do not have to solve my reaching problem if nothing needs reaching."*

That is the correct shape and it inverts the current design: `PostCompact` should
carry the CONTENT of the recovery, not instructions for fetching it. Which also
answers (3) — a curated block that fits, rather than a 50KB pointer.

**And the thing to protect specifically:** the summary's verbatim section of the
USER's own messages. Every ruling honoured after the compaction came from there rather
than from memory of it. That section is why the session stayed coherent.

## 5. Open spans: unanswerable, and the reason is the answer

It never ran `--back=1`, so it never saw them. **A span is only as good as the read
that never happened** — and what it got wrong in (2) is precisely what a span would
have told it: whether that builder's work was open or closed.
