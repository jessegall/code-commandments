# plan-tree

**Jesse's design, and his structure.** A main plan, and a sidequest nested under
whatever you were doing when it appeared — recursively, as deep as you like.

```
orchestrator/plan/<plan>/README.md
                         references/<file>.md
                         sidequest/<name>/README.md
                                          sidequest/<name>/…
```

**The path IS the breadcrumb.** Not a list of tasks — a stack of interruptions that
can be read back. Finishing means deleting the folder, and the path tells you where
you were.

## The properties the folders give, which a flat store cannot

- **The path is the parent chain**, so there is no parent field to keep in sync.
- **Deleting is finishing**, so there is no `status: done` to recreate a graveyard.
- **A sidequest nobody closed shows up in `git status`** as a folder nobody removed.
- **It is durable and in git** — reviewable in a diff, alive tomorrow, readable from
  a fresh session. This is the whole reason it does NOT live under `sessions/<id>/`.

## Read through tooling, always

Jesse: *"it's all read using tooling, right? It's just that it's nice and organised
that way."* Yes. The folders are storage; nobody opens them by hand. Same as the
profile, which `orchestrate show` reads and `orchestrate profile traps "…"` appends
to with the day and sha stamped.

- `tree` — the whole shape, with depth. Read once after a compaction.
- `where` — the path from root to here, one line per level. The command that answers
  "what was I doing" at any depth.
- `up` — close this level and surface one.
- `add <name>` — a sidequest UNDER wherever you are, never a path you must spell.
- `stale [--for=N]` — a live branch untouched for N. The plan-shaped twin of
  "N items are waiting on YOU".

## Scoping — the same split as a profile

A plan has two halves, and confusing them is what makes it either die with a
terminal or collide between two.

- **Durable: the tree itself** — `orchestrator/plan/<name>/`, in git. The work, its
  sidequests, their READMEs. A multi-day port's plan is the thing a fresh session
  most needs, so it cannot live under `sessions/<id>/`.
- **Session: the cursor** — WHICH plan this session is working, and WHERE in it.
  Meaningless tomorrow, and correctly dies with the session.

This is exactly how a profile already works: its content is durable in
`orchestrator/profiles/<name>/`, and `Instance` holds which one is in force for this
session. A plan is `use`d the same way.

**So concurrent sessions do not collide.** One session works `dissolution`, another
works `tooling` — different trees, both durable. The only real clash is two sessions
on the SAME plan, which is the same case as two orchestrators on one board and has
the same answer: the second is told it is a reader.

## Decided

**The verb is not `plan`.** `commandments plan done/stuck/status` is plan-EXECUTION
— branch, phases, the end gate — and the skill draws a hard line on it: an
orchestrator that starts executing has stopped orchestrating. Two meanings on one
verb would blur exactly that. Open: `build tree` / `build where`, or a `board` alias
that reads more naturally in front of a noun.

**Closing writes the reason to the journal, then deletes the folder.** A conclusion
can be re-derived; a reason is what lets a later reader see why the premise held. So
the tree stays a map of live work and the reasoning goes where reasoning lives.

**No depth limit** — the depth IS the information; a four-deep sidequest is telling
you something true about the day. **No auto-detection of distraction.** **No status
field.**

## Why it matters, from the build that needed it

A lane reported and went twenty-five minutes unanswered: its findings were acted on,
the lane never was, and there was no record of the level it came from. Every signal
said fine. Nothing distinguished *done and handled* from *done and waiting on me*.

And a 118-condition flat gate failed not because it was long, but because the answer
to nearly all of them is "not yet" — neither met nor blocked — and a flat list has
nowhere to put that. A tree gives "not yet" a home: a live child of a live parent,
which is a place rather than a status.
