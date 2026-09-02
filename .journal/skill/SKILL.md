---
name: journal
description: "The journal in this project: when to tag, when to declare work, when to pin and when not to, when to search the transcript instead of answering from memory, and what to do when a hook holds or denies you. Load it when a hook has held you or denied a call, when you are about to edit and nothing is open, when a context warning asks for a decision, when you need something said earlier in the session, or right after a compaction or a fresh start when the injected block names pins or open work you do not recognise."
---

# The journal

A compaction keeps what was **done** and drops what was **decided**. The transcript on disk
loses nothing. The journal is the index that gets you back to it, and the small set of
facts that must be handed to you again after the loss.

Everything runs through one script:

    .journal/journal.py <command>

`journal` is an alias for it, added to the shell rc by `install.py --alias`, and it works
from a tool call wherever the shell sources that rc. The path works everywhere. This
skill writes the path; either is fine.

## When to load this skill

Load it when one of these is true. Otherwise the rules in the injected block are enough.

| you are here                                                        | read           |
|---------------------------------------------------------------------|----------------|
| a hook held your stop or denied a tool call and the reason is unclear | *If a hook holds or denies you* |
| you are about to edit and nothing is open                            | *Declare work* |
| a context warning says nothing else runs until you decide            | *Pin, or say nothing* |
| the user rules something that must hold on every track, or asks to promote a pin | *A rule is a pin for every track* |
| the user says "later", "not yet", "after X", or you are told to leave something for now | *Delayed work: the to-do* |
| you are about to say "I think we decided…" or "as discussed…"        | *Look before you answer* |
| the session just started or compacted and the block names pins or open work you do not recognise | *Read the transcript back* |
| you are on the wrong track, or want a new one                        | *Tracks* |

Do not load it before every message. The tag rules are in the block you were handed.

## Three ideas that explain every rule

**A tag is free; work costs a command.** A tag rides on a message you were sending anyway,
so nothing has to be remembered and there is no store to keep current. Declaring work is a
commitment, so it costs a verb, and that cost is the thought.

**A tag describes the message it rides on, and nothing else.** That is why none of them can
be wrong. `[!update]` was struck because its correctness depended on something outside its
own message, an open scope. Progress is `journal update` now, a command, because it is
about the work.

**Refuse rather than guess.** Every command here fails loudly rather than file something
plausible in the wrong place, and every gate names its own way out in the message. A note
under the wrong heading reads as true, and nothing about it looks broken afterwards.

## Tag every message

One tag, at the very start. Talking *about* a tag is not using one.

| tag              | use it when the message…                                              |
|------------------|-----------------------------------------------------------------------|
| `[!discovery]`   | reports the real shape of something you did not know: a bug's cause, a constraint in the code, a fact you measured |
| `[!correction]`  | says something you had wrong is now right, including your own earlier message |
| `[!blocked]`     | says you cannot proceed, and on what                                  |
| `[!info]`        | reports something happening that is not work progress: an agent started, a long build running, a nudge explained |
| `[!reply]`       | answers what was asked, directly. Routine; kept out of the digest      |

**When in doubt, `[!reply]`.** It is honest for any answer, and it is what makes the rule
keepable: every message can carry a tag, so the check needs no judgement. A message with
no tag filed nothing, and the Stop hook will say so once.

**Only the last message of a turn is judged.** A turn is one answer delivered in pieces:
a connective line, a tool call, another line, then the thing you actually meant. The
scaffolding needs no tag. If the user interrupts, the last piece becomes the message, and
the hold you get for it is correct.

## Declare work

    .journal/journal.py start "<the work, in your own words>"
    .journal/journal.py update "<what moved>" [--on="<work>"]
    .journal/journal.py end "<the same words>"

**When: before the first write, not before the first read.** Edits, `Write`, `cat > file`,
`sed -i`, `rm`, `git commit`: all denied while nothing is open. Reads are never gated,
because the reading is what tells you what the work is. Read until you can name it, then
`start` it, then edit.

**A good subject is a sentence you will say again.** "fix the gate" is not; "stop the
write gate reading heredoc bodies as shell" is. `end` matches the subject you opened with.

**`update` is for where it got to**, not for every step. Write one when a reader on the
far side of a compaction would otherwise find a start and nothing after it: a decision
made inside the work, a dead end, a change of approach. `update` refuses when nothing is
open, and refuses to guess when several things are; name one with `--on`.

**`end` asks one question**: did this teach anything a later reader would get wrong
without? Answer it then, because it is only answerable then. "Nothing" is the usual answer
and a fine one.

## Pin, or say nothing

    .journal/journal.py remember "<the claim, in one line>"
    .journal/journal.py nothing "<why nothing here needs pinning>"
    .journal/journal.py pins [--all]
    .journal/journal.py pins <n> --full
    .journal/journal.py strike <n> "<why it stopped being true>"

Rules, pins and open work are the **only** things handed back after a compaction, and they
are handed to **every** session that starts in this project. Tagged messages are not: they
become retrievable, not present.

**When to pin: all three, or none.**

1. Somebody **decided** it. The user ruled, or you chose between options and the choice is
   not obvious from the code.
2. The next reader would get it **wrong** without it. Not "would not know", *wrong*: they
   would use the rejected option, the old name, the constraint backwards.
3. It will still be true **tomorrow**.

A status, a count, a percentage, or what you just did fails the third and rots into a
confident falsehood wearing the same authority as the facts that still hold.

Pins that earned their place:

    the user ruled motion.ts FORBIDDEN — the CSS transition replaces it
    the context ladder never climbs a guessed window: context_window set, or the peak has ruled out every window but one
    a subagent's hook payload carries the PARENT's session_id and transcript_path; only agent_id tells it apart

Pins that did not:

    converted 14 of 22 components          a status; wrong by tomorrow
    tests pass on the rethink branch       a count; wrong by the next commit
    I refactored the gate into two functions   what you just did; the diff already says it

**Never cite the scratchpad, or anything under `/tmp`.** Those paths exist for one session
and are gone or unreachable for the next, so a pin naming one is refused. A report worth
remembering goes into the repo, and the pin cites that; or the pin carries the report's
claims and not its location.

**A pin is a claim, not its reasoning.** There is a hard length limit and no limit on
count. The argument stays in the transcript: every pin records where it was written, and
`pins <n> --full` prints the conversation around it. A pin over the limit is denied before
the command runs; the fix is to cut it to the claim, never to split the reasoning across
two pins.

**When the context warning arrives, decide.** The Stop hook warns at 50%, 70%, 90% and
95% of the window, once each, and after each one **no other tool call runs** until you
have run `remember` or `nothing "<why>"`. This is a gate because the warning alone was
measured and did not land. It forces a decision, not a pin: `nothing` with a reason is
exactly as good a way through as a pin, and it is the right one more often than not. The
journal's own commands still run, so `search` and `--back=1` are there to decide with.

**A rule is a pin for every track.**

    .journal/journal.py rule "<the ruling, in one line>"
    .journal/journal.py rules [--all]
    .journal/journal.py rules <n> --full
    .journal/journal.py rule --strike <n> "<why>"
    .journal/journal.py promote <n>

A pin says what this line of work decided. A rule says what the *project* decided, and
switching tracks never moves it: "subagents never write the journal", "components are
state-only from here on". It is handed to every session first, above the track's pins.
Same three questions, plus one: **would it be wrong on any other track?** If it would only
be wrong on this one, it is a pin. Write a rule directly when the user rules something
project-wide; `promote <n>` when a pin turns out to bind every track. Promote strikes the
pin and says where it went, so one claim never stands in two places.

**Strike what stopped being true.** `strike <n> "<why>"` retires a pin without inventing
a replacement; `remember --supersedes=<n>` replaces one. Struck pins stay readable under
`pins --all`. Nothing here evicts on a counter.

## Delayed work: the to-do

    .journal/journal.py todo "<title>" [--brief]   add one; --brief reads a longer brief from stdin
    .journal/journal.py todo                  the titles, numbered
    .journal/journal.py todo <n>              the whole brief
    .journal/journal.py todo start <n>        open work with that title; `end` closes both
    .journal/journal.py todo done <n> "<how>" resolved without starting it
    .journal/journal.py todo drop <n> "<why>" abandoned, on the record

A pin is a claim, a rule binds, open work is in flight. A to-do is work that was **put
off**: something the user said to do later, or something you found and were told not to
touch yet. It is a file under `todo/<track>/`, titled, with a brief in the body, and it
belongs to the track that deferred it.

**Park it the moment it is deferred.** The usual shape: you are in the middle of one
thing and the user asks for another that is not what you are working on and can wait.
Do not switch, do not hold it in your head, and do not answer "later" with nothing
written. Write it: `journal todo "<title>"`, say in your reply that it is parked as to-do
n, and carry on. The same when the user says "later", "not yet", "after X", or tells you
to leave something alone for now.

**Not when it is imagined.** "The user said: after the merge, convert the last three
widgets" is a to-do. "It might be nice to refactor this" is not; that is a message with a
tag, or a question to the user.

**Title, then brief.** The title is what the list shows, so it names the work in a few
words. The brief is what you will need in a week: what exactly, why, where to start, and
what the user said. Pass it with `--brief` on stdin:

    .journal/journal.py todo "convert the last three widgets" --brief <<'EOF'
    After the merge. Dropdown, Trail and EditorPanel still read props; the user wants
    them state-only like the others. Start from src/View/Widgets/Dropdown.php.
    EOF

**A to-do is not permission.** The start block lists what is waiting and a stop with
nothing open says so once. Neither is an instruction to start one: the user decides what
is picked up and when. Pick one up with `todo start <n>`, which opens the work under the
same title, and `end` closes both.

## Look before you answer

    .journal/journal.py search <term>

This is the reflex the whole tool exists for, and the one most easily skipped.

**Search when any of these is about to leave your mouth:**

- "I think we decided…", "as discussed…", "earlier you said…"
- the name of a command, flag, option, file or setting that the user chose or rejected
- "the user wants X" where X was said more than a summary ago
- anything about work that was open when a compaction or a session start happened
- an answer to "why did we…", "didn't we already…", "what was the reason for…"

A compaction keeps roughly 25,000 characters standing in for the whole session, and it
keeps what was *done* far better than what was *decided*. A half-remembered ruling feels
like knowledge and is subtly wrong: the wrong command name, the option that was rejected,
the constraint backwards. That is worse than an admitted gap, because nobody questions it.

`search` prints line numbers, which are citations you can quote. If it comes back empty,
say the record does not have it rather than filling the space. Checking costs one command;
guessing costs the user their own decision.

## Read the transcript back

    .journal/journal.py                  the conversation since the last compaction
    .journal/journal.py --back=1         the stretch the last summary REPLACED
    .journal/journal.py user             the user's own words, in full, never trimmed
    .journal/journal.py open             work declared and never closed, with its notes
    .journal/journal.py carry            exactly what a session start hands back

**After a compaction**, before you touch anything: `--back=1` is precisely the stretch the
summary replaced, and `user` is the half of it a summary drops first. Read them.

**At a fresh start**, the block names the standing pins and any open work. Open work from
an earlier session is listed so you know it exists; you are not held for it. Before you
continue it, `open` shows where it got to, and `search` on its subject shows the rest.

The CLI reads **this session's** transcript, found through `CLAUDE_CODE_SESSION_ID` in the
environment of every Bash call.

**Subagents do not write the journal, and they do receive the rules.** A subagent may
`search` or read `pins` and `open`; its `start`, `remember`, `switch` and the rest are
denied, and none of its tool calls are gated or nudged. It is handed the rules on its
first tool call and again at 25%, 50% and 75% of its own window. It reports back, and the
main conversation decides what to file.

**Rules come back at every context rung** for the main agent too, inside the hold: a
block read at the start is far behind by the time the window is half full.

## Tracks

    .journal/journal.py tracks           every track, current one marked
    .journal/journal.py switch "<name>"  park this one, pick up that one
    .journal/journal.py switch --back    the one you came from

A track is a line of work, not a session. Switch when the user says a new piece of work
should not inherit the pins and open work of the current one. Rules are not parked by a
switch; they hold on every track. Switching parks a track
exactly as it stood; nothing is ever deleted by switching. Tracks are shared, so switching
moves every session in the project.

## Shared, and not shared

The record, meaning rules, pins, work, to-dos and tracks, is the project's. Every session and every agent
reads and writes the same one. What is not shared is the hook's bookkeeping: where it last
held you, which context rung it announced, whether a pin is due, the largest tool result.
Those are facts about one transcript and live in `runtime/<transcript>.json`, so a fresh
session starts with clean marks and a full store.

## When something seems broken

    .journal/journal.py verify           wired, fired, and in THIS session?

Those are different facts and it reports each, because a hook that is registered and
silent looks exactly like one everybody is obeying. It also says when the context window
is unset, in which case the ladder is silent rather than guessing.

## If a hook holds or denies you

Read what it says and do that one thing. Every hold names its own way out.

| it says                                                     | do                                                        |
|-------------------------------------------------------------|-----------------------------------------------------------|
| *journal: N message(s) carried no tag*                      | tag your next message; it will not hold for those lines again |
| *journal: N piece(s) of work still open*                    | `end` it, or `update` where it got to; said once per piece of work |
| *Nothing is open, so this edit would not be filed*          | `start` the work, then edit                                |
| *journal: context N% full — decide before any other tool runs* | `remember "<claim>"` or `nothing "<why>"`; until then no other tool runs |
| *That pin would be refused*                                 | cut it to the claim, or drop the scratch path; the reasoning is in the transcript |
| *`journal <verb>` from a subagent is refused*               | you are a subagent: report what you found, the main conversation files it |
| *THAT … CALL RETURNED N CHARACTERS*                         | nothing to undo; read narrower next time if you were after one thing |

The one line is the whole instruction. The reasoning behind it arrives as a collapsed
context block; read it when the line is not enough.
