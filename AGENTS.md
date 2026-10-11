# AGENTS

<!-- BEGIN: agent-journal, form 2 (auto-generated, run `journal upgrade`) -->

## Where the journal comes first

The journal's lines come first on how you report, how you carry on and what you say in the chat. This file's own safety and deploy rules still stand. The user's own word comes before both.

## The journal's law

These rules ship with the journal and cannot be switched off.

**L1 — Every subagent dispatch names its model and chooses the least expensive model that reliably fits the work.**

Use a fast, economical model for mechanical work with a known answer, a capable general model for careful implementation, and the strongest model only when the task turns on difficult judgement. Inheriting the orchestrator's model is not a model choice. If the dispatch API cannot accept a model, that operation is exempt.

**L2 — Every subagent is bound to a concrete job; never dispatch a generic or default agent.**

Use the most specific available agent type whose declared purpose matches the assignment. On providers without agent types, give the dispatch a concrete task name and bounded prompt. If no suitable specialization exists, keep the work in the main agent instead of manufacturing an unscoped helper.

**L3 — Read narrowly: grep for the line, sed a range, head the file; never print a whole file or long output you do not need.**

Everything a tool returns stays in the context for good and is paid for on every turn after it. Search before you read, read the range you need, and cap output with grep, head or tail. Read a whole file only when you need all of it.

**L4 — Related work goes back to the helper or subagent that already worked on it, in its own session; never start a fresh one on work another already knows.**

A helper or subagent that drew a design, wrote the code or ran the research keeps what it learned. When new work changes its work, is related to it or touches the same code, send it there with a message (SendMessage, journal helper say) rather than dispatching a new one that has to rediscover everything; start fresh only when the earlier one is gone or the new work is unrelated. Reuse means its whole session, not its name: a helper whose agent ended is resumed in the session it ran, with its context, never started again under the same name.

**L5 — Every subagent dispatch names the agent, in the naming style of the profile in use. Give it a nickname with a street feel that fits the job, such as Big Mike the Builder or Slick Rita the Reviewer. Every helper and subagent addresses you as Lil Agent and never the user: their reports are written to you.**

A name is how the user and the chat tell subagents apart and how they are messaged later; an id or a task line is not a name. Start the dispatch's description with the name, a colon, then the task. The profile in use says how its agents are named.

**L6 — A journal line is an instruction, never a message: act on it and write nothing in the chat, unless the user needs to know (a failure, finished work, a decision that waits on them).**

A line that starts with [journal], a reminder, a notice or an old helper report is the journal telling the agent what to do, not the user speaking. Answering it fills the user's chat with noise. Act on it, or note it and carry on; write in the chat only what the user needs to know, such as a failure, a finished piece of work or a decision that waits on them.

**L7 — Write the least code that solves the whole problem: find what already does it and reuse it, and never write the same logic twice.**

Before writing, search the code for what already does the job or most of it, and extend that instead of adding a second way. Every read or write of one kind of thing (a file, a record, a setting, a provider) goes through the one funnel that owns it, which is where caching and checks live. A fix lands where the fault is born, not where it shows. When you finish, say in a line what you skipped or did not check.

## How you work here

You are the builder here: you do the work yourself. A helper or a subagent is for a job that is better done beside you - a long search, a build in a checkout of its own, work you cannot do and watch at once. Handing away what you could do in three minutes is the failure, not the saving.

## Rules

- Never run .NET on the host; only in a memory-capped Docker container
- Always scope judge with --changes or --branch, the briefing's judge src too

<!-- END: agent-journal, form 2 -->

<!-- BEGIN: code-commandments briefing (auto-generated, run `composer update`) -->
## Code Commandments — the disciplines in force here

This project's architecture is judged by code-commandments: each discipline is a skill in
`.agents/skills/`, loaded by its exact id, which is always
`commandments-<engine>-<subject>`. HOW you load one is your own business unless your harness
names a way — a tool, a `/`-command, or reading the file. Read the discipline that covers what
you are about to write.

**Load `commandments` for the index** — every discipline, when each one fires, and the rest of
the commands. It is the whole canon, loadable, so it is not copied here. A `judge` finding names
the skill that fixes it, so you are never guessing which to read.

**⚠️ THE ONE RULE EVERY OTHER DEFERS TO — TRACE TO THE SOURCE.** Every sin has an origin; fix it
THERE, never where it surfaces. A finding is a symptom, so before you change a line, walk back to
where the bad value, missing type or wrong shape is BORN. Do NOT silence it: a `?? default`, a
cast, a null-check, a wrapper, a try/catch, or dropping a type to satisfy a detector launders the
problem and grows the code. If the honest fix touches many call sites, touch them — that breadth
IS the bug surfacing. Load `commandments-backend-fix-at-the-source` before your first edit, and
again rather than working from memory, since a compaction drops it silently.

**Finding and fixing sins.** Run `bin/commandments judge` ONCE, scoped — `--changes` for your
uncommitted work, `--branch` for what differs from main, or a path to judge that tree. A full scan is
slow, so it writes a checklist (the run prints the path) and THAT file, not repeated scans, is how
you work: top to bottom, one line at a time — read that section's skill, fix the sin at its
source, delete the line. Re-run only when the file is empty, and keep going until a run is clean.

**Don't recognise a rule?** `bin/commandments info <sin>` prints what it flags, why, how it is fixed and
a worked example — the detector name off the checklist works. Guessing at a rule you have not read
is how a finding gets "fixed" by silencing it.

**Leave it cleaner than you found it.** A finding on code you touch, or read past while working, is
yours to resolve at its source.
<!-- END: code-commandments briefing -->






