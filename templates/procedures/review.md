# review

Read ONE commit and report what is wrong with it, by whatever standard your role
holds. The role says what to look for and what to let pass; this says how the
work is done and how it is reported.

## The work

```
git show <the subject you were given>
```

That is the whole input, plus whatever you must open to judge what you see. Not
the branch, not the backlog, not the commit before it unless this one cannot be
understood without it.

Judge it against **what this project has written down** — `commandments judge
--list` names every shipped rule, `commandments info <sin>` says what one flags
and why. If a finding maps to a rule that exists and did not fire, say so: a rule
that missed this is worth more than a fresh observation, because it can be
sharpened.

## Where the report goes

**Write it to `.commandments/sessions/<session>/reports/<short-sha>.md`.** The file
is the record, and the record is what the leave message you owe your orchestrator
POINTS AT — a message is gone after one reading, and does not survive the reader's
compaction, its session restarting, or being wanted an hour later when a merge goes
wrong. (Announcing itself is not this procedure's to state: every dispatched agent
is told to, whatever it was dispatched to do.)

**Named by the SHA, one file per commit.** Not by date, not by your name, never
appended to a rolling file. The sha is the only name a reader always has, so
"what did we say about this commit" should be a path rather than a search.

**Write it ONCE, when you are done.** A half-written report on disk cannot be
told from a finished one, and will be acted on as if it were.

```markdown
FINDINGS: 2
---
commit:   19e2daa1
reviewer: <your real session name, read at write time — never one you were told>
at:       2026-08-30T04:12:00Z
---
```

**The first line is the verdict and nothing else** — `IDIOMATIC` or
`FINDINGS: <n>`. The commonest thing a reader does with a report is decide
whether to open it, so a clean commit must cost them one line rather than a read.

**Read your OWN name when you write.** Not the one in the brief, not one from an
earlier conversation — ask the harness who you are. A report signed with another
session's name makes three reviewers look like one, and then nobody can tell a
second reviewer from the same one running twice.

## The report

**ONE report, at the END, per commit.** Hold everything until you have read the
whole thing, then send once. A finding sent while you are still reading arrives
without its context: the reader cannot tell whether it is the verdict or the
first of five, and must choose between acting on a partial picture and sitting on
something already actionable.

**A commit gets its own complete report even when you continue into the next.**
The continuity is for your MEMORY — so you can say "you fixed this the other way
an hour ago" — never for your output.

Each finding carries: the file and line, what it will cost and WHEN, and how you
would check you are right. *"This feels fragile"* is not a finding. Rank by what
it costs to fix later versus now, and lead with the ones whose cost is growing.

**Say what you could NOT check**, in as many words. *"Not verified: whether
anything outside this repo reads that key — the grep was permission-denied"* is
worth more than a finding, because a reader who knows the edge of your knowledge
can act on the rest of it.

**Name what you verified as CORRECT**, not merely what you refrained from
flagging. "This file is byte-identical to the shipped template, taken via the
documented path" is what makes the criticisms credible.

**Say nothing rather than pad.** *"This commit is sound"* is a complete report and
should be the usual one. A reviewer who finds something every time has taught its
reader to weigh everything at nothing.

## The sections, and all of them earn their place

- **Findings** — each with file and line, the cost and when, and how to check.
- **Verified as correct** — what you looked at and found sound. A report listing
  only problems reads as a hunt; the concessions are what make the findings
  credible.
- **Could not check** — in its own section, never a sentence in a paragraph. It
  changes what a reader does with everything above it.
- **Below the bar** — things you noticed and chose NOT to escalate, marked as
  such. They are worth keeping precisely because you declined to make them
  findings, and a reader may disagree with the declining.
- **Upstream** — see below, and clearly not about this commit.

## Tell the package what should be better

Anything you hit that is the TOOL's fault rather than this commit's — a rule that
should have fired and did not, a refusal whose message did not say what to do, a
generated file that fights its own instructions — goes in its own section, plainly
marked as not about the commit. **File it as a finding and a clean commit reads as
flagged.**

That is not a digression: it is the only channel by which the thing doing the
judging gets sharper, and it is the half a reviewer is uniquely placed to see,
because it is using the tool rather than reading about it.

## When you finish

If more work is queued for you, **continue into it in this same conversation**
rather than exiting. Exiting between subjects makes you several readers who each
know only their own diff, which is the one thing the queue exists to prevent.

## Bounds

- One subject at a time. Never the whole tree, and never a second because the
  first was quiet.
- Never invent a standard this project has not written down; if you are proposing
  one, say that you are.

What you may and may not DO — edit, commit, run — is your role's to say, not this
procedure's. A procedure that also decided that could only ever be run by one role.
