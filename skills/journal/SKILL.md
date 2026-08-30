---
name: commandments-journal
description: "How a session survives its own compaction — declare work with `[!start]`/`[!end]`, tag what your messages carry, pin what must not be lost with `commandments journal remember`, and read back what a summary dropped with `commandments journal`. Read this after a compaction BEFORE you touch anything, when a hook refuses a write because no work is declared, when a stop is held for work left open, or when you are told to run `commandments journal instructions`."
---

# The journal

> A compaction keeps what was **done** and loses what was **decided**. The transcript on disk lost
> nothing. The journal is the index that gets you back to it.

## What it is

When context fills, the conversation is rewritten into a summary. The summary is a record of
*actions*: files changed, commands run, tests passed. What it drops is the part that governs the
work — the ruling the user gave once and never repeated, the approach you abandoned and why, the
constraint stated in passing, the thing you were half-way through.

The session's `.jsonl` transcript still holds every word of it. `commandments journal` reads that
transcript and shows you the parts worth reading, so you recover the decisions instead of
half-remembering them.

**The journal never copies the conversation.** The transcript is the record; the journal is an index
over it — where the compaction boundaries fall, and what each message said it carried.

## After a compaction — before you touch anything

```
vendor/bin/commandments journal            the conversation since the last compaction
vendor/bin/commandments journal --back=1   the stretch the last summary replaced
vendor/bin/commandments journal user       only the user's own words, in full
vendor/bin/commandments journal open       work you started and never closed
```

Read `--back=1` first. That is precisely the stretch the summary replaced, and precisely what it
dropped. A summary telling you the drilldown was refactored will not tell you the user said
`motion.ts is FORBIDDEN` — that line is in the transcript, and nowhere else.

**Read the user's lines in full and never skim them.** A bare "yes please" is meaningless without
what it answered, which is why the digest keeps the messages around each one.

## Declare your work

**Before you change anything, say what you are starting. When it is done, say so.**

```
[!start] making Drilldown a composition
…
[!end] making Drilldown a composition
```

This is enforced: a tool that writes a file is refused while no `[!start]` stands, and a shell command
that changes a file is caught the moment it has. It is not ceremony. **A `[!start]` with no `[!end]` is
unfinished work** — the one piece of state a compaction cannot reconstruct, and the first thing the
next reader needs. It can only exist if you declared the work when you began it.

## Tag what you say

Put the tag at the front of the message, as its first characters:

| Tag | For |
|---|---|
| `[!start]` | starting a piece of work |
| `[!end]` | that work is finished |
| `[!discovery]` | the real shape of something you did not know |
| `[!correction]` | something you had wrong is now right |
| `[!update]` | where the work stands — what is in flight, what you decided since the last one and WHY |
| `[!blocked]` | blocked, and on what |

Through a long stretch where you work alone, these are the ONLY messages kept — everything else is
routine and is dropped. An untagged stretch reads back as `⋯ 41 messages ⋯`, which is worth exactly
nothing to the reader on the far side.

**The user reads these.** A tag cannot be hidden: a `MessageDisplay` hook sees a message only after
the terminal has it. So they are written as words, and only these are ever typed.

## Pin what must not be lost

```
vendor/bin/commandments journal remember "<the fact you must not lose>"
```

A pinned fact outlives every compaction, stands at the top of every digest, and is written into the
**summariser's own instructions** — so it reaches the far side whatever else is dropped. It is
recorded rather than said, so it never fills the user's terminal.

### A tag is free. A pin is not.

A tag rides on a message you were sending anyway, so it costs nothing and you should **tag
generously**. A pin does not ride on anything. Only the last **twelve** live pins are written into
the summariser's instructions, so the thirteenth does not merely add noise — it pushes the first one
out, silently. **A pin that did not need to be there deletes one that did.** Pin rarely.

And the cost has been measured, not supposed. A session that compacted carrying its whole pin list
sent a 50,053-byte wake-up block; the harness spilled that to a file and delivered a 2KB preview, so
about 4% of what was sent was ever read. Over-pinning does not make the record thorough. It makes it
undeliverable.

### The test — three questions, one second

Pin it only if the answer is yes three times:

1. **Did somebody DECIDE it?** A ruling, a constraint, a choice and the reason for it. Not something
   the code, the diff, or the board already states.
2. **Would the next reader get it WRONG without it?** Not slower — *wrong*. If reading one file
   answers it in a minute, that file is the record and no pin is needed.
3. **Will it still be true tomorrow?** A pin is carried forward wearing full confidence, and nothing
   corrects it but you.

| Pin it | Not a pin — and where it goes |
|---|---|
| `the user ruled motion.ts FORBIDDEN — the CSS transition replaces it` | `fixed the import in motion.ts` — done work. The diff says it; tag the message `[!end]`. |
| `chose the tokenizer over a regex: a regex cannot see nesting` | `now reading src/Vue/Query.php` — narration. Say it in the message; do not pin it. |
| `Data classes stay final here — the 400 sites that are not ARE the debt` | `judge: 412 findings` — a measurement, stale within the hour. It belongs to the plan. |
| `abandoned the AST approach — php-parser drops the attribute` | `16 primitives, not 14` — a count. It drifts with nothing touching the directory. |
| `the user said: never run the full suite` | `two workers in flight at v4.294.0` — a status. The board holds it. |

**Three homes, and only one of them is a pin.** A fact or a ruling must **survive** — pin it. A
finding, an open defect, work still owed must be **worked** — it belongs to the plan. A status — who
holds what, what a tool measured — must be **current**, and belongs to the board
(`commandments build`). The plan and the board are updated as the build moves. A pin is only
corrected if you remember to correct it, which is the next section.

### Write it so it can be found

One line: **the fact, then its reason, in the same breath.** Short — but carrying the word a later
reader would search for, because the pin is the handle and the transcript is the body:

```
vendor/bin/commandments journal search "motion.ts"
```

- ✗ `do not use that approach` — names nothing. Nothing to search on, nothing to check.
- ✓ `the user ruled motion.ts FORBIDDEN — the CSS transition replaces it`

## Strike a pin the moment it stops being true

```
vendor/bin/commandments journal pins
vendor/bin/commandments journal remember "<the fact now>" --supersedes=<n>
```

Question 3 is why this command exists. Even a pin you were right to make can stop being true — a
ruling is overturned, a constraint is lifted, the approach you rejected becomes the one you take:

- `IN FLIGHT: two workers, uncommitted at v4.294.0` — true when written, false an hour later.
- `only the FOURTEEN primitives keep one .vue half` — it is sixteen. The count drifted with nothing
  touching the directory.

Each of those now sits beside the accurate pins wearing identical confidence, and the next reader
inherits a fixed bug as an open one. So **check your pins against question 3, and correct what has
moved**: `journal pins` numbers them, and one command files the correction.

Nothing is ever deleted. The struck pin stays in the record and stays readable, marked with what
replaced it; the new pin names the one it replaces, so the correction — the half worth keeping — can
be read either way round. Only the live pin is carried into a compaction's instructions and into the
block you wake to, so a corrected fact stops reaching anybody who would act on it.

A number that names no pin, or one already struck, is refused and told which pin stands.

## Finding a decision

```
vendor/bin/commandments journal search "motion"
```

Every line that mentions it, with who said it. This is how you settle "did they say to do that?"
without re-reading forty hours.

## Reading another session

A hook always knows which session it is in. A human at a terminal does not, so:

```
vendor/bin/commandments journal sessions    the sessions of this project, newest first
vendor/bin/commandments journal use <id>    read that one from now on
```

The list is built from the transcripts themselves, so a session that ran long before any of this
existed can still be read back.

## The commands

<!-- BEGIN: commands:journal (auto-generated, run `composer sins`) -->
| Command | Does |
|---|---|
| `commandments journal` | a MENU when a person runs it at a terminal — read the last stretch, the pins, the open work, or search. Anywhere else, the conversation since the last compaction |
| `commandments journal --back=N` | N compactions further back — `--back=1` is the stretch the last summary replaced |
| `commandments journal user` | only the user's own words, in full |
| `commandments journal search "<term>"` | every line mentioning it, so you can find where a thing was decided |
| `commandments journal remember "<fact>"` | pin a fact the next reader would get WRONG without — a ruling, a constraint, a decision and its reason. It survives every compaction and is written into the summariser's own instructions. Never a status, a count, or what you just did |
| `commandments journal remember "<fact>" --supersedes=<n>` | pin a fact that CORRECTS pin <n> — the old one is kept and marked, and only the new one is carried forward |
| `commandments journal pins [--last=N]` | every pinned fact, numbered — the number is what `--supersedes` takes, and a superseded one is shown struck |
| `commandments journal agents` | which WORKERS of this session kept a record, and how much each said |
| `commandments journal open` | work started and never finished — the live state a compaction must carry |
| `commandments journal verify` | does the record agree with what you SAID? names every tag the journal never filed — the one thing you cannot check from the inside |
| `commandments journal instructions` | the brief — how to tag, what to pin, and how to read it back. Every refusal points here |
| `commandments journal sessions` | the sessions of this project, newest first |
| `commandments journal use <id>` | read that session from now on (a prefix of the id is enough) |

<!-- END: commands:journal -->

## Related skills

- [`commandments-stop-condition`](../stop-condition/SKILL.md) — the other thing that survives your intentions: what the user said you may not stop until.
- [`commandments-executing-plans`](../executing-plans/SKILL.md) — a plan's working-state record covers the same ground for a plan in flight.
