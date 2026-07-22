---
description: Set a stop condition — you may not stop until it holds (code-commandments)
argument-hint: <condition, e.g. the full test suite passes>
---

The user has set a **stop condition** for this session: `$ARGUMENTS`

Do this now, in order:

1. Record it: run `vendor/bin/commandments until "$ARGUMENTS"` (keep the user's wording; rephrase
   only to make it a checkable statement rather than a task).
2. Load the `commandments-until` skill (Skill tool) and follow it — it is the discipline for working
   under a gate.
3. Get to work on making the condition hold. From here every stop you attempt is held by the Stop
   hook and sends you back to verify it. When you have **verified** it holds (actually run the
   command / read the output — never assume), run `vendor/bin/commandments until met <n>`. If you
   are genuinely blocked, run `vendor/bin/commandments until stuck` and tell the user what blocks
   you. Never `until clear` to escape a condition you haven't met.
