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

## Principles

- **Proactively implement + close.** Don't report-and-wait or leave it for the human — fix
  surfaced issues and close them (a `Closes #N` trailer auto-closes on push to the default branch).
- **Triage ALL open issues**, not only `[detector-report]`-titled ones.
- If acting on an issue means OUR finding (in this repo or a consumer) is itself wrong, file a
  `report` rather than working around it.

## What to read when

| Read | When |
|---|---|
| `reference/triage.md` | The decision tree + the exact reproduce→fix→fixture→release→close flow. |

Shipping the fix → see [[releasing-and-propagating]].
