# What makes a good handoff

The auto-filled snapshot is facts; the value is in the narrative you write. The
test: **could someone with zero prior context resume in one read, without asking
you anything?**

## Section by section

**Goal** — one or two lines on the OUTCOME, not the activity. "Migrate every
registry to a lazy container-resolving singleton so package:discover stops
building Data objects at boot" — not "working on registries".

**State**
- *Done*: each finished piece WITH its commit short-sha, so the reader can `git
  show` it. "Phase 1 lazy singleton population (a1b2c3d); Phase 2 EmitterSet split
  (e4f5g6h)."
- *In progress*: what's half-done and exactly how far.
- *Remaining*: an ORDERED list — the order you'd actually do them in.

**Next step** — the single, concrete next action. "Extract `DefinitionRegistry`
→ `PipeDiscovery` + a dumb store; tests catch breaks." Not "continue the refactor".

**Decisions & deferrals** — choices already made (so they're not re-litigated) and
anything deliberately deferred WITH the reason. "Deferred the AI-request VO (#7)
— FP-prone, needs Jesse." This is what stops a fresh context from undoing your
thinking.

**Resume notes** — the cold-start kit: key files to read first, non-obvious
gotchas, and the exact verify command (`vendor/bin/phpunit`, the gate, a smoke
test). Convert relative dates to absolute.

## Smells of a bad handoff

- "Continue where I left off" with no specifics — useless.
- Listing what you DID (a changelog) instead of what's NEXT and what's DECIDED.
- Vague remaining work with no order or next step.
- Stale: it describes a state three commits ago. Overwrite it as you go.
