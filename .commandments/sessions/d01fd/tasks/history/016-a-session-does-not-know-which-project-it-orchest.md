# a session does not know which project it orchestrates

FOUND BY DOGFOODING, on the second command of the tool's own documented routine. docs/routine.md says 'setup <name> from inside the project being built', but setup's destination is fixed relative to the binary and nothing records WHICH project it was run for. So from a session, 'agent probe' runs git in ../orchestrator-sessions/self — not a repo — and dies with a raw git error. Exit 1 is honest; the message is not. Fix at the source: setup records the project root it was run for, agent uses it, and a session with no project says so in its own words.

- queued 2026-08-30 17:59
- done 2026-08-30 18:02 — setup records the git root from ORCH_CWD; agent opens the worktree there under .lanes/; a session with no project explains itself
