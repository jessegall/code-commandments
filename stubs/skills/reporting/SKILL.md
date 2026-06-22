---
name: commandments-reporting
description: How to report a genuinely-wrong prophet finding (false positive / wrong rule) so it gets FIXED — `report` files a GitHub issue and quiets the finding until the issue is answered; `reports --check` lifts it when resolved. Read this before you absolve, work around, or `--no-verify` past a finding you believe is wrong.
---

# Reporting — when a finding is wrong, report it (don't bury it)

## Purpose

The commandments only get better if wrong findings come back as issues. So when
a finding is **genuinely wrong** — a false positive, or a rule that doesn't apply
here — do NOT silently `absolve` it, hand-work-around it, or `--no-verify` past
it. **Report it.** `report` files a GitHub issue on the package repo AND records a
**report-linked absolution** so the finding stays quiet until the issue is
answered; `reports --check` (wired into session start) lifts that absolution the
moment the issue is resolved, so a real fix flows back automatically.

## When to use this skill

- A prophet flags code that is **correct** — a false positive → report it.
- A prophet's **rule is wrong** for this codebase / this case → report it.
- A prophet **crashed**, mis-fixed (bad `repent`), or missed an obvious case
  (false negative) → report it.
- You are tempted to `absolve` "just to get past it", or to `git commit
  --no-verify` around a finding you think is bogus → **stop and report instead**;
  → read `reference/when-to-report.md` (report vs absolve vs fix).

This is NOT for findings that are *correct but inconvenient* — fix those, or
absolve a genuine advisory LEAVE with a reason.

## What to read when

| Read | When |
|---|---|
| `reference/when-to-report.md` | Deciding report vs absolve vs fix — is the finding actually WRONG, or just inconvenient? |
| `reference/lifecycle.md` | The full flow: `report` → it quiets the finding → `reports --check` polls/lifts on resolution → re-judge / `composer update`. Includes the poll loop. |

## The command (one line)

```
vendor/bin/commandments report --at=path:line --reason="why this is wrong"
```

- `--at=path:line` (the locator `judge` prints) ties the report to the finding so
  it goes **quiet until answered**, and infers `--prophet`/`--file`/`--line` — so
  you do NOT pass `--prophet` separately. Add `--prophet=NAME` only to disambiguate
  two findings on the same line.
- **Without a locator** (`--at` or `--fingerprint`), `report` files the issue but
  records **NO** absolution — the finding still blocks. It says so. Always pass
  `--at`.
- Reporting a **sin** quiets it until the issue is answered, but a sin is never
  *absolved* — `absolve` applies only to advisory WARNINGS with a genuine LEAVE
  reason. A sin is fixed, or reported-if-genuinely-wrong; nothing else clears it.

Then, **after a wrong-finding report, ALWAYS arm a poll loop in the same breath**
— a wrong-finding `report` without a watch loop is half-finished (nothing brings
the resolution back into THIS session). (A `--feature-request` records no
absolution, so it needs no loop — see below.)

```
/loop 15m Run `vendor/bin/commandments reports --check`. If a report-linked
absolution was lifted, run `composer update jessegall/code-commandments` and
re-judge: a real false positive is gone; a genuine sin re-blocks and must be fixed.
```

Session-start `reports --check` also re-checks on every NEW session, so the report
resolves itself either way — but the loop is what catches it without a restart.

## Proposing a NEW rule or feature (not a wrong finding)

`report` above is for an EXISTING finding that's wrong. When you instead want to
PROPOSE something new — a new prophet/rule, a CLI enhancement — there is no finding
to tie to, so use the feature-request mode:

```
vendor/bin/commandments report --feature-request \
    --title="Flag anemic model mutations" \
    --reason="why this rule/feature is worth it" \
    --proposed-prophet=EncapsulateModelMutation \   # optional, for a new-prophet idea
    --rubric="APPLY WHEN … / LEAVE WHEN …"           # optional
```

It needs NO `--prophet`/`--at`/`--fingerprint`, files an `enhancement`-labelled
issue, and records NO absolution (a proposal has nothing to quiet) — so no poll
loop is needed either. Use this instead of overloading `--prophet` with a
non-existent name or bypassing the pipeline with `gh issue create`.

## Backs

This is the positive twin of the `report` / `reports` commands and the standing
rule: *a wrong finding is a bug to file, not a checkbox to dismiss.*
