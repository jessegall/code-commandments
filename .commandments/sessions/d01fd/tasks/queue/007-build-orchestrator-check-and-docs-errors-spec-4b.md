# build orchestrator check and docs/errors (SPEC 4b)

Depends on 4a's resolver existing first — a checker for tokens cannot precede the tokens. Includes the file:line diagnostics and the rule that must not blur: an unknown KEY is silently ignored (forward compatibility), a malformed LINE is warned (a mistake nobody knows about).

- queued 2026-08-30 16:59
