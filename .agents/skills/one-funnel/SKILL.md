---
name: one-funnel
description: Before writing ANY code in code-commandments that branches on a language, a bridge, an engine or an event kind, or that does something another file already does (in Go, hook.py, a bridge, a script), climb this ladder. Load at the start of every session and before every edit that touches more than one language's path. Rule 21; report 42 lists what breaking it cost.
---

# One funnel

A language, a bridge and an event kind are DATA flowing through one code path. Only parsing differs. Every time this
was forgotten, the same logic was written per language, the copies drifted, and the user paid: five bridge caches
keyed five ways made worktree edits take 40-60 s instead of one second; a dozen language switches disagreed on what a
comment is; C# grew its own read path; the release pin is read in six places.

## The ladder: climb it before you write anything. Stop at the first rung that answers.

1. **Does a funnel already do this?** Search for the concern, not the language: `grep -rn` the verb (cache, read,
   label, comment, fence, sweep, queue, fetch, pin) across `cli/`, `engine/`, `contract/`, `bridge/`, `scripts/`,
   `.journal-plugin/`. The known funnels: `contract/languages.go` (a language's facts: extensions, label, comment form,
   generated files), `cli/source` (which engines judge a language), `cli/scan` (`Bridges`, `ReadShared`, `kept.go`:
   every bridge's read and its one content-keyed cache), `catalog` (engines to languages), `cli/atomic` (writes),
   `cli/help` (every help surface). Found it: put your change THERE, once. Stop.
2. **Is what you are about to write a fact about a language, a bridge or an event kind?** Then it is a row in that
   funnel's table, never a `switch`, an `if language == ...`, or a map in your own file. Add the field to the table and
   read it. Stop.
3. **Does it already exist in another file, in any language?** Go and hook.py, two bridges, a script and the binary:
   the second copy is the defect. Make one own it and the others ask it or receive it as data. Stop.
4. **Is it a cache, a key, a lock, a retry, a sweep or a download?** It belongs to the funnel every language passes
   through, never to one bridge or one script. A bridge parses and names what it read; it keeps nothing. Stop.
5. **Only now, when every rung said no:** write it once, parameterised by the one thing that differs, in the shared
   package of its concern, with a test that every language reaches it the same way.

Say which rung answered in your task log, so the next agent sees the funnel you used.

## Signs you skipped the ladder

- `switch language`, `case source.PHP:`, `if kind == "mypy"`, a map keyed by language outside a facts table.
- A default that silently means one language (`return PHP`).
- The same constant (a timeout, a file name, a version pin) typed in two files.
- A fix you are about to make "in each bridge" or "in Go and in hook.py".

Each is a missing funnel: stop, climb the ladder from rung 1, move it into the funnel, delete the copies.

## When you find one you cannot fix now

File it as a critical task naming every copy by `file:line` and the funnel that should own it. Never leave it in words.
