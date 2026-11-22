# Triage decision tree + flow

## Decision tree for a [detector-report]

```
Read the issue body AND every comment.
│
├─ Is the flagged code actually correct (the detector is WRONG)?
│     → FALSE POSITIVE: tighten the detection (AST/semantics, a principled reject —
│       never a name list). Add a fixture from the reported shape. Release (patch).
│       Close with a comment explaining the guard.
│
├─ Is the RULE wrong / mis-scoped for this case?
│     → adjust the rule or its per-detector config; fixture; release; close.
│
├─ Did it CRASH / MISS something (false negative)?
│     → fix the engine / scribe / detection; fixture; release; close.
│
└─ Is the finding actually CORRECT (the reporter was wrong)?
      → do NOT change the detector. Close the issue WITH A REASON explaining why it's
        working as intended — the reporter must fix their code.
```

## The flow

1. `gh issue list --state open` → `gh issue view <n> --comments` → pick one, read the comments.
2. Reproduce: a sample of the reported shape run through the rule (`commandments rule try`), or a sin
   marker on it in the language's fixture.
3. Fix in the rule file under `rules/` — or what it reads (a shared definition in `rules/definitions/`, a
   package's facts in `rules/packages/`, a fact the bridge must write), never a name list. If the sin's
   wording was the problem, sharpen the `sin` block's description.
4. Add/extend the fixture + test; `scripts/dev go test` the packages you touched.
5. The pre-commit hook regenerates the generated documents (`composer sins` by hand).
6. Commit on your branch (no attribution trailer) with `Closes #N` → [[releasing]]. Merging and tagging are
   Sir Jesse's.
7. Comment the resolution on the issue.

## Report-back

Closing the issue IS the signal back: a real fix reaches every consumer on their first
`composer update` after the release that carries it; a "works as intended" close tells the reporter the finding stands and their
code is what must change. No manual relay needed.
