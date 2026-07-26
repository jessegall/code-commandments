---
description: Set a stop condition — you may not stop until it holds (code-commandments)
argument-hint: <condition, e.g. the full test suite passes>
---

The user has set a **stop condition** for this session: `$ARGUMENTS`

Do this now, in order:

1. Record it: run `vendor/bin/commandments until "$ARGUMENTS"` (keep the user's wording; rephrase
   only to make it a checkable statement rather than a task).
2. Surface it: add the SAME condition to your to-do list (TodoWrite) as a pending item, so the user
   can see at a glance what is holding you. Keep it in sync — mark that item completed when you run
   `until met <n>`.
3. Load the `commandments-until` skill (Skill tool) and follow it — it is the discipline for working
   under a gate.
4. Get to work on making the condition hold. From here every stop you attempt is held by the Stop
   hook and sends you back to verify it. When you have **verified** it holds (actually run the
   command / read the output — never assume), run `vendor/bin/commandments until met <n>`. If you
   are genuinely blocked, run `vendor/bin/commandments until stuck` and tell the user what blocks
   you. Never `until clear` to escape a condition you haven't met.

If several conditions stand, drain them: one that needs a decision from the user does not stop the
others, so do everything you can on your own first and hand back with only the genuine blocker (and
every question at once). `until stuck` claims the WHOLE list is blocked.

Steps 1 and 2 are one action, not a choice between two. That holds whenever the user defers
something — "add it to the to-do list", "don't forget to…", "later" — because a tracker item holds
no stop and is gone with the session: the gate is what brings the task back, the to-do item is what
makes it visible. Doing only the tracker loses the task quietly.
