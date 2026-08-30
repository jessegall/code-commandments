# a session copies SPEC.md and stories/ it does not need

Found by running setup: ../orchestrator-sessions/verify carries the 600-line spec and the stories folder. Harmless but wrong — those are development artifacts of the slate, not part of a working orchestrator, and every session pays for them. The copy is deliberately unconditional (SPEC 1), so the fix is where those files LIVE, not a growing exclusion list.

- queued 2026-08-30 16:59
- done 2026-08-30 18:09 — SPEC.md and stories/ live in dev/; the copy excludes .git, engine and dev by category
