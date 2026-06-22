# The report lifecycle — file it, then let it resolve itself

```
1. report  ─────────────►  files a GitHub issue on the package repo
                           + records a report-linked absolution (the finding
                             goes QUIET — it survives the post-commit reset and
                             stays until the issue is answered)

2. reports --check  ────►  runs at SESSION START (it's in the session-start hook).
                           Re-checks each report-linked issue:
                             • issue answered/closed  → lifts the absolution
                             • still open             → stays quiet

3. after it lifts  ─────►  re-judge:
                             • real false positive → gone after `composer update`
                               (the prophet was fixed in a new release)
                             • genuinely a sin     → it re-blocks; now fix it
```

## File it

```
vendor/bin/commandments report --prophet=NAME --at=path:line --reason="why this is wrong"
```

Always pass `--at=path:line` (or `--fingerprint=…`) — without a locator the issue
is filed but the finding is NOT quieted (it keeps blocking). The locator also
infers `--prophet`/`--file`/`--line` from the finding.

## Poll it actively (optional)

Session-start `reports --check` already polls on every new session. If you want to
poll *within* a long session without restarting, set up a loop:

```
/loop 15m Run `vendor/bin/commandments reports --check`. If a report-linked
absolution was lifted, run `composer update jessegall/code-commandments` and
re-judge the file: a real false positive is gone; a genuine sin now re-blocks and
must be fixed. If nothing lifted, do nothing.
```

## Don't double-file

If a finding is already reported, `report` reuses the existing issue and keeps the
finding absolved — it won't open a duplicate.
