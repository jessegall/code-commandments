# commandments install should wire a commit-msg guard refusing AI attribution

Jesse's rule: no commit message credits an AI — no Co-Authored-By, no session link, no generated-with, no robot. Verified today that main carries none (13 exist only on old side-branches, newest 2026-06-17, none reachable from main, so nothing published or tagged is affected and there is nothing to amend). A .git/hooks file is local and dies with a fresh clone, so the durable fix is for install/sync to wire it like every other hook it stamps. The orchestrator repo already does it via a committed dev/hooks + core.hooksPath.

- queued 2026-08-30 19:45
