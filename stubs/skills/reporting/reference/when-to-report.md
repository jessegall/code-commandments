# Report vs absolve vs fix

Three outcomes for a finding. Pick by asking **"is the rule wrong here, or is my
code wrong here?"**

| Situation | Do this |
|---|---|
| The code is genuinely correct and the prophet is **wrong** (false positive), or the **rule itself** doesn't belong here | **`report --at=path:line --reason=…`** — file it so the prophet gets fixed. It quiets the finding until the issue is answered. |
| The code is genuinely **wrong** (the prophet is right) | **Fix the code.** Sins should be fixed; auto-fixable ones via `repent`. |
| It's an advisory **WARNING** whose rubric's LEAVE-WHEN genuinely applies here | **`absolve --reason=…`** — a reasoned, one-off exception. Not a dismiss button. |
| It's a **SIN** in pre-existing / out-of-scope code you must NOT touch in this change | **`absolve --at=path:line --reason=…`** — a sin CAN be absolved single-target with a reason (a deliberate, audited escape so you're never wedged). FIX is still the default; batch absolve never sweeps a sin. |

## The trap to avoid

Do **not** reach for `absolve` or `git commit --no-verify` just to get past a
finding you *suspect* is bogus. That buries the signal: the prophet stays broken,
every other consumer keeps hitting it, and you've hidden it locally. If you
believe a finding is **wrong**, that belief is exactly what a `report` is for —
it costs one command and it's how the rule gets fixed for everyone.

## "Correct but inconvenient" is not a reason to report

If the prophet is *right* and you simply don't want to do the refactor, that's not
a report — it's either a fix (do it) or, for an advisory warning, a reasoned
absolve. Reserve reports for findings that are actually **incorrect**.

## False negatives count too

If a prophet **missed** something it clearly should have caught (or `repent`
produced a wrong fix, or a prophet crashed), report that too — same command, the
reason describes what it missed / got wrong.
