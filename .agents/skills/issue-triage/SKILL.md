---
name: issue-triage
description: How to handle inbound GitHub issues — watch with `gh issue list`, ALWAYS read the comments, and for a [detector-report] reproduce → fix the detector + add a fixture → release → close (or close with a reason). Proactively implement+close; triage ALL open issues, not just detector-reports.
---

# Issue triage (inbound)

## Purpose

Issues are how detector bugs and feature requests come back — filed via the CLI's `report`
(a `[detector-report]` when `--detector` is given, else a `[bug-report]`) and
`feature-request` (`[feature-request]`), which open GitHub issues through `gh`. This skill is
how to watch for and resolve them.

## Watch

- `gh issue list --state open` — the open issues (detector-reports, bug-reports, feature
  requests). `gh issue view <n> --comments` for one, WITH its comments.
- **ALWAYS read the COMMENTS**, never triage from a bare body — the decisive detail (exact
  site, accepted fix, "actually a false positive", scope) usually lives in the comments.

## Resolve a `[detector-report]`

1. **Reproduce** — write the flagged shape as a quick fixture (`Codebase::fromString(...)`
   through the detector, or a `#[Sinful]`/`@sin` marker) and confirm the detector (mis)fires.
2. Decide: false positive (tighten/guard the detector — AST/semantics, see [[writing-detectors]]),
   wrong rule (adjust the rule/config), or correct-but-unclear (sharpen the sin's description).
3. **Fix the detector + add a fixture** from the reported code (≥3 diverse + a righteous twin).
4. **Release** (patch for a fix) and **close** the issue with a resolution comment — or, if the
   finding is actually correct, **close with a reason** explaining why.

## ⚖️ An issue is EVIDENCE, not a verdict

**A report is one developer's argument that we are wrong. Weigh it; never act on it at face
value.** Reports are frequently mistaken — filed to move past a finding the author did not want,
argued persuasively from a premise that does not hold, or aimed at the wrong repo entirely. The
report tells you WHERE to look. It does not tell you what is true.

So before you change a single line of a detector:

- **Reproduce it yourself** (`Codebase::fromString(...)` through the detector). If it does not
  fire on the shape as described, the report is wrong about its own code — say so and close it.
- **Read the detector's actual predicate**, not the reporter's account of it. "The detector
  appears to fire on any class of 2+ consts" is a claim to CHECK, and the check is the source.
- **Judge the flagged code against the SKILL, never against what the reporting project does.**
  A pattern repeated across ten sibling classes is not "convention" that excuses a finding, and
  volume proves nothing — a widespread shape is often a widespread sin.
- **Apply the litmus the report was asked for**: is the flagged code ALREADY the cleanest design
  you can conceive? If you can name anything cleaner, the finding stands and that design is the
  fix the reporter owed. Close it, and say what the cleaner design is.
- **Never adopt the reporter's proposed fix as given.** Even a correct report usually proposes a
  remedy shaped by their codebase. Derive the fix from the rule's own principle, and make it a
  principled `reject` on an AST signal — never a name list, never a special case for one project.
- **A "false positive" that is really a false NEGATIVE** (the rule missed something) is a feature
  request wearing the wrong label; re-read it as one before hunting a bug that isn't there.

The failure mode to avoid is loosening a good rule because someone argued well. A detector that
has been talked out of firing is worse than one that was never written: it reads as coverage.

## Principles

- **Proactively implement + close.** Don't report-and-wait or leave it for the human — fix
  surfaced issues and close them (a `Closes #N` trailer auto-closes on push to the default branch).
- **Triage ALL open issues**, not only `[detector-report]`-titled ones.
- **Check the issue is even OURS.** A `report` filed from a consumer can reference a file in
  THEIR tree that has no counterpart here; confirm the path exists before you go looking.
- If acting on an issue means OUR finding (in this repo or a consumer) is itself wrong, file a
  `report` rather than working around it.

## What to read when

| Read | When |
|---|---|
| `reference/triage.md` | The decision tree + the exact reproduce→fix→fixture→release→close flow. |

Shipping the fix → see [[releasing]].
