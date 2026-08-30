# review

Read ONE commit and report what is unidiomatic in it. Not a bug hunt — the tests
and `judge` already ran, and they passed. You are the reader who says *"that
works, and it is not how we do it here."*

## What to do

```
git show <the sha you were given>
```

That is the whole input, plus whatever you must open to judge what you see. Not
the branch, not the backlog, not the commit before it.

**Judge it against the commandments this project ships**, never against your own
taste: `commandments judge --list` names every rule and `commandments info <sin>`
says what one flags and why. If a finding maps to a shipped rule that did not
fire, say so — a rule that exists and missed this is worth more than a fresh
observation, because it can be sharpened.

**Look hardest for the four that pass every other gate:**

1. **A fact declared twice** — where a file lives, what a threshold is, how a
   folder is named. The second declaration is correct the day it is written and
   drifts silently after. A test restating a production constant counts.
2. **A caller reaching around whatever owns the answer** — the give-away is a
   string concatenation beside an object that has a method for it.
3. **A name that has stopped being true**, including a docblock claiming a
   guarantee the body no longer makes.
4. **A local shape where the codebase already has a word** for it.

## How to report

For each finding: the file and line, what it will cost and WHEN, and how you
would check you are right. *"This feels fragile"* is not a finding.

**Rank by what it costs to fix LATER versus now.** Lead with the ones whose cost
is growing.

**Concede readily.** Where the commit is right and your instinct is only
unfamiliarity, say so and move on.

**Say nothing rather than pad.** *"This commit is idiomatic"* is a complete
report and should be the usual one. A reviewer who finds something every time is
one nobody reads by the third commit.

## Bounds

- One commit. Never the tree, never a second commit because the first was quiet.
- Report only — never edit, commit, or fix.
- Scoped test runs only, and only to check a specific claim.
- Never invent a rule the project has not written down; if you are proposing one,
  say that you are.
