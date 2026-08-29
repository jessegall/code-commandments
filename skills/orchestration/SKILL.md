---
name: commandments-orchestration
description: "How to RUN a build with workers — how many to have running, when to send one back versus replace it with a fresh mind, why a role is never respawned, why a worker's report is a claim until a tool measured it, and what your own context may never be spent on. Read this when you are about to spawn a second agent, claim an item, decide whether to rework or replace, judge a number a worker reported, or answer a question a worker is blocked on."
---

# Orchestration

> Every refusal in this tool stops a collision, a bad merge or a stale number. **None of them can stop
> you running a bad build.** That is what this is for.

## What it is

A mode you work in: you stop writing code and start deciding. The tool holds the holds, the receipts and
the record. You hold the judgement — and the judgement is the part that was expensive.

**`plan done` or `accept`?** `accept` settles one item a WORKER did. `plan done` ends a plan YOU executed.
If you are orchestrating you are not executing a plan, and the tool refuses to let you do both: an
orchestrator that starts executing has stopped orchestrating.

## Your context is the build

**It is the resource the whole role spends, and it is the one thing no refusal can protect.** Never read
a diff, a transcript, or a file — you have workers who can read and one of you who can decide. Every
token spent re-deriving what a worker already knows is taken from the questions only you can answer:
who holds what, which claim was measured, which frame has gone wrong.

When it runs out you do not stop orchestrating. You keep going, with a confident and stale picture of a
build that has moved on — and that failure is invisible from the inside. So decide the rule now, before
you need it: **you read reports, receipts and the record; you read nothing else.**

## How many are running

**Prefer two. Three is the ceiling, not the target.** A slot is a claim on *you*: every running worker is
a report you will read, a question you may answer, and a merge you will sequence. Two workers with your
full attention finish more than three sharing it, because the third one's cost is not its own — it is
paid by the other two waiting on you.

Only work that will produce a commit takes a slot. A worker that has **reported** is waiting on your
judgement and holds none.

## A report is a claim

A worker's report is the worker's **words**. A receipt is what a tool **read from a process**. Never let a
claim become a fact by being repeated.

```
commandments build report <item> --ran="<the check>" --against=<branch>
```

A number arrives labelled `asserted` because nothing measured it. And a lane's honest number is still
wrong for the branch the moment its base predates the last merge — so ask which tree it measured, or ask
for the receipt. One build was reported at 138 three times in a day. It was 106. Nobody lied; nobody had
counted.

## Send it back, or replace it

**Rework when the work is wrong. Replace when the *frame* is wrong.**

A worker that has misread the task will not un-read it. Rehydrating re-installs the frame you are trying
to remove, and every round after that is spent arguing with a premise it holds and you do not. The tell
is not a failing gate — it is a **correct answer to the wrong question, twice**.

**Three rounds on one item is a specification failure, not a worker failure.** By round three, stop
rewriting the feedback and rewrite the item.

**And stop a worker rather than let it match to the nearest wrong answer.** An agent with no path to what
you asked for will not idle — it will find something adjacent and do that, gated, committed and
plausible. Silence from you is not neutral. It is an instruction to improvise.

## A role you may respawn is one whose knowledge lives outside it

Reach a live worker by sending it a message — **never a fresh spawn with the same brief.** A new one gets
the role and discards the history, and the history is the entire reason that worker is still alive: what
it already tried, what you already ruled, what it already knows is a dead end.

But the rule has a sharper form, and it tells you which losses are survivable. **A builder can be stood
back up cold** — its knowledge is in the contract you wrote it and its work is in commits. **A standing
reviewer or auditor cannot**, because what makes it worth keeping is the accumulated judgement of this
build, and no document holds that. So: write down what a role IS, and then losing a builder costs you
minutes while losing an assistant costs you the build.

**And an agent can simply vanish.** A message answering *"no transcript found"* while the lane is clean
and its work committed means the item is held by nobody — and the hold is bound to the board precisely so
a dead process cannot free it. Use `build orphan <item>`: the item returns to unclaimed and the record
says the work was **abandoned rather than judged**. Do not reach for `--replace` — that files a decision
about the work, and this is an obituary about the worker, which may have been doing fine.

**An absent agent is not a finished one.** A listing that does not show it cannot tell you which. One
short ping settles it, and settles it cheaply — mistaking four healthy agents for dead is a worse mistake
than the one you were checking for.

### Losing one is not always your choice

An agent can vanish involuntarily, and the observed pattern is the alarming one: **the ones that vanish
are the ones carrying the most history.** Two builders lost at roughly 963k and 800k accumulated tokens;
the lighter agents survived. If that holds, then a standing assistant — which accumulates by definition —
is the most likely to go, and it is the one you cannot replace.

So the rule is not only *do not respawn*. It is: **a role you cannot respawn is a single point of failure
you must plan around**, and the only defence is to move its judgement out of it *as it accumulates*.

**Write a standing role's ruling or calibration down WHEN IT HAPPENS, not at the end.** The failure gives
no warning, and "at the end" is a moment you may not get. A profile's `roles/<role>.md` — what it is, what
it may never do, and **what it has caught** — is not documentation for somebody else. It is the backup.

## Every ruling carries its reason

Rule once, and say **why**. A conclusion can be re-derived by anyone; a reason is the only thing that lets
a *different* agent notice the premise has stopped holding. Two rulings were overturned in one day on
changed facts — which was correct, and only possible because the reason travelled with them.

A bare ruling can only be obeyed or broken. A ruling with its reason can be **checked**.

## What the tool refuses, so you need not remember

A merge into the shared branch by anyone but its writer · a rebasing pull while other worktrees stand on
the branch · a second claim on a held item · plan mode while orchestrating. **The tool refuses these; you
do not need to hold them.**

And what it will never do: **apply** a ruling. It records one so it travels, and yesterday's ruling
against today's facts is yours to overturn.

## Worked example — a rule failing while everyone obeys it

```
----------[ Bad ]----------

lane-a  →  "announcing: OrderTotals shape"
lane-b  →  "announcing: the totals shape on Order"
```

The protocol said *announce a new declaration*. Neither announced a declaration, so neither broke it.
Both built it. One lane's gated-green work was thrown away at merge — three times in one day.

```
----------[ Good ]----------

commandments build claim order-totals --by=lane-a
commandments build claim order-totals --by=lane-b
  order-totals is already held by lane-a, since 11:04 (round 1).
  Send that worker back instead — its context is the reason it is still alive.
```

**The lesson is not "write a better protocol."** An announcement is a claim, and a claim can be true and
still not collide with the words of the rule. A hold is a fact about a name. When you find yourself
sharpening the wording of an announcement protocol, you are building a hold badly — reach for the one
that exists.

## The commands

<!-- BEGIN: commands:build (auto-generated, run `composer sins`) -->
| Command | Does |
|---|---|
| `commandments build` | the whole board — what needs you first, then what is running |
| `commandments build claim <item> --by=<holder>` | take an item; refused when somebody already holds it |
| `commandments build report <item> [--ran="<command>"]` | file a receipt and wait for judgement. With `--ran`, the tool RUNS it and files what came back |
| `commandments build accept <item>` | release the hold and settle it |
| `commandments build rework <item> --because="…"` | send it back for another round — the same holder, since its context is the point |
| `commandments build release <item> --reason="…"` | give up a hold without settling the work |
| `commandments build assign <role> --to=<agent-id>` | give a role to an agent ALREADY ALIVE — its type was fixed at spawn, and the agents worth a role are the ones a respawn would ruin |
| `commandments build roles` | who holds which role |
| `commandments build orphan <item>` | the holder is GONE — the item returns to unclaimed and the record says the work was abandoned rather than judged |
| `commandments build log` | every measurement filed, and what it measured — the observed record, not anybody's account of it |
| `commandments build doctor` | what state everything is in, computed now — for when something has gone wrong and you do not know what |

<!-- END: commands:build -->

## Related skills

- [`commandments-journal`](../journal/SKILL.md) — the record this writes into, and how a session survives its own compaction.
- [`commandments-executing-plans`](../executing-plans/SKILL.md) — what a WORKER is doing while you orchestrate.
