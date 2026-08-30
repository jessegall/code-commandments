# doc suffixes name the kind, not the format

SPEC 8d in the orchestrator repo. docs/agent.help, docs/bad-key.error, docs/check.info. Makes the kind addressable — a diagnostic renders docs/%(kind).error, and check can pair every .error doc against every error the code raises.

- queued 2026-08-30 19:36
