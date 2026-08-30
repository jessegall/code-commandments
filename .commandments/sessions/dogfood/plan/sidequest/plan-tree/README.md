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

## Scoping — everything here is the session's

A plan and its sidequests are **entirely session-scoped**. The orchestrator folder
holds profiles and nothing else.

This is simpler than the two designs it replaces, and it deletes work rather than
adding it: nothing is shared, so nothing can collide, so there is no symlink, no
lock, and no second-orchestrator question to answer.

**What makes it safe is that the SESSION is the durable unit.** A session holds its
own plan, so it is the thing you come back to — and a session can be NAMED, its
folder renamed to match, with `sessions/.names` recording which name belongs to which
id. `sessions/dissolution/plan/` is a plan you can return to by saying its name.

So the split is not plan-versus-sidequest. It is:

- **`orchestrator/profiles/<name>/`** — a WAY OF WORKING. Durable, in git, reusable
  across every session and every plan.
- **`sessions/<name>/`** — a RUN OF WORK. Its plan, its sidequests, its board, its
  journal, its stop conditions. Named so it can be found again.

## Closing a sidequest — the reason goes UP

Closing writes the REASON and removes the folder. The reason is appended to the
PLAN's README, one level up, rather than into the journal.

Both live in the session, so this is no longer about surviving a boundary — it is
about where a reader looks. A conclusion reached in a detour belongs with the work it
was a detour from, not in a chronological index of everything said.

## The verb: `orchestrate plan`

Namespaced rather than renamed. `commandments plan` is plan-EXECUTION and keeps that
meaning; `commandments orchestrate plan` is this tree. The word is right for both and
the owner is what disambiguates.

```
orchestrate plan            the tree, with depth — read once after a compaction
orchestrate plan where      the path from root to here, one line per level
orchestrate plan up         close this level and surface one
orchestrate plan add <name> a sidequest UNDER wherever you are
orchestrate plan stale      a live branch untouched for N
```

## NOT BUILT YET

Every plan folder in this session was written by hand. The structure is being used
before its tooling exists, which is deliberate — two designs have already died from
being described rather than coded, and a tool built first would have been the tool
built wrong.

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
