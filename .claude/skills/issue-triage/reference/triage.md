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
├─ Did it CRASH / mis-fix (bad repent) / MISS something (false negative)?
│     → fix the engine / scribe / detection; fixture; release; close.
│
└─ Is the finding actually CORRECT (the reporter was wrong)?
      → do NOT change the detector. Close the issue WITH A REASON explaining why it's
        working as intended — the reporter must fix their code.
```

## The flow

1. `gh issue list --state open` → `gh issue view <n> --comments` → pick one, read the comments.
2. Reproduce: a minimal fixture exercising the detector — `Codebase::fromString(...)` through
   the detector in a quick test, or a `#[Sinful]`/`@sin` marker on the reported shape.
3. Fix in `src/Detectors/{Backend,Frontend}/<Name>Detector.php` — or the shared engine helper
   it composes (`src/Ast/`, `src/Vue/`, a per-package `*Node`), never a name list. If the sin's
   wording was the problem, sharpen its `description` in `src/Sins/`.
4. Add/extend the fixture + test. `vendor/bin/phpunit tests`.
5. `composer readme` / `composer sins` if a description, the detector table, or the command
   surface changed.
6. Commit (no Co-Author) with `Closes #N`, new semver tag (patch=fix), push commit + tag →
   [[releasing-and-propagating]].
7. Propagate to consumers (per-consumer `composer update`, commit-only). Comment the resolution
   on the issue; verify it auto-closed.

## Report-back

Closing the issue IS the signal back: a real fix reaches every consumer on their next
`composer update`; a "works as intended" close tells the reporter the finding stands and their
code is what must change. No manual relay needed.
