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

## Scoping — a plan is shared, a sidequest is this session's path

The split is not "durable vs volatile" but **what the thing is a fact ABOUT**.

- **A PLAN is a fact about the work.** The multi-day port exists whoever is running
  it. It is durable, lives in `orchestrator/plan/<name>/`, and is in git.
- **A SIDEQUEST is a fact about this session's path through the work.** The detour
  THIS orchestrator took, in the order it took it. Entirely session-scoped, and it
  correctly dies with the terminal it happened in.

So the root folder holds plans and nothing else. The nesting — the breadcrumb, the
stack of interruptions — belongs to the session, because it is a record of one
orchestrator's route rather than of the work.

## The symlink is the binding AND the lock

An orchestrator REFERENCES a plan by symlinking it into its own session folder.

That one artifact does three jobs, which is why it beats a separate lease file:

- **It binds** — the session's own folder says which plan is in force, the way
  `Instance` says which profile is.
- **It locks** — a live symlink IS the claim. Nothing to write, nothing to expire.
- **It is legible** — `ls -l sessions/*/plan` shows every orchestrator and what it
  holds. The record cannot disagree with itself, because there is only one copy.

And it cleans itself up: the link dies when the session folder does, so an
orchestrator that crashed leaves no lease anybody has to reap.

**Use {@see Agents\SkillLink}, not a bare `symlink()`.** It already handles the cases
this will meet — relative on POSIX, absolute on Windows (whose `symlink` resolves a
relative target against the process cwd), and a content-idempotent COPY fallback
where the filesystem has no links at all.

## A second orchestrator on the same plan

Refused for writing, allowed for reading — which is the answer already decided for
two orchestrators on one board, arriving here unchanged.

The refusal names who holds it and since when, the way a duplicate `claim` does. It
is a refusal rather than a prompt because the cost is paid before anyone could read a
warning: two orchestrators editing one plan produce a tree that is wrong in a way
neither can see, which is the failure this whole design exists to prevent.

## Closing a sidequest — the reason goes UP, not to the journal

Closing writes the REASON and removes the folder. But the journal is ALSO
session-scoped, so writing the reason there would lose it on exactly the boundary the
sidequest already dies on.

**So the reason is appended to the PLAN's durable record.** The session keeps the
path it took; the plan keeps what was learned. That is the only way a conclusion
reached in a detour reaches tomorrow's orchestrator, and it is what makes deleting
the folder safe rather than lossy.

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
