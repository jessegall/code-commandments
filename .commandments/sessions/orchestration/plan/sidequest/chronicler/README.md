# chronicler

**Jesse's design.** A default role that writes the session's narrative — the lore
between the facts.

Not `scribe`: `src/Scribes/` already owns that word for the `repent` rewriters, and
"check what the scribe wrote" must not be ambiguous between prose and PHP.

## Why it is not a summary

Three records exist and each answers a different question — git says what CHANGED,
the journal says what was DECIDED, the board says who HELD what. **None holds the
connective tissue**: that a fix was found because a peer measured something, that a
design died because someone had to describe reconciling it. A summary of the three
would be redundant; the account is the part that exists nowhere.

## Open

- **A HANDLER on the orchestrator hooks**, not bespoke machinery — every moment it
  cares about is already an event something else produces.
- **Fires on COMPACTION above all**, since that is the one moment the narrative is
  provably about to be destroyed rather than merely at risk. `PreCompact` already
  fires and already reads the journal.
- Also on events that mark a chapter — commits since the last entry, a merge, a plan
  level closing, an item accepted. **Never a bare timer**: periodic firing never once
  caught a state change, because the harness had already reported it.
- **Reads its sources at fire time** — journal, `git log`, the board — so it states no
  fact it did not compute.
- **Costs the orchestrator nothing.** Dispatched, not resident: it reads, writes, and
  returns only that it wrote. Never send the orchestrator to re-summarise; that
  produces the drift it exists to prevent.
- Writes to the session's tracked plan folder, so the lore is durable with the session.
- Ships as a DEFAULT profile role any project can retune or switch off. Its triggers
  and interval are profile content, in a diff, like `routine.md`.
- **Appended to, never rewritten** — one continuous account in order, not a second
  journal of entries.
