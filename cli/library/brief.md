## Code Commandments — the disciplines in force here

This project's architecture is judged by code-commandments: each discipline is a skill in
`{{library}}/`, loaded by its exact id, which is always
`commandments-<engine>-<subject>`. HOW you load one is your own business — a tool, a `/`-command,
or reading the file. Read the discipline that covers what you are about to write.

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

**Finding and fixing sins.** Run `{{binary}} judge src` ONCE — pass any path to scope it, or
`--changes` for your uncommitted work and `--branch` for what differs from main. A full scan is
slow, so it writes a checklist (the run prints the path) and THAT file, not repeated scans, is how
you work: top to bottom, one line at a time — read that section's skill, fix the sin at its
source, delete the line. Re-run only when the file is empty, and keep going until a run is clean.

**Don't recognise a rule?** `{{binary}} info <sin>` prints what it flags, why, how it is fixed and
a worked example — the detector name off the checklist works. Guessing at a rule you have not read
is how a finding gets "fixed" by silencing it.

**Leave it cleaner than you found it.** A finding on code you touch, or read past while working, is
yours to resolve at its source.
