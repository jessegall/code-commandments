# orchestrator kill cannot stop a live process

Named honestly by the builder in docs/kill.md rather than faked: the build never captures a PID or session handle for whatever the pasted claude command starts, so kill frees the orchestrator's own accounting only. Either capture the handle at agent-start, or keep it as-is and make the doc the contract. Decide which.

- queued 2026-08-30 16:59
- done 2026-08-30 18:11 — decided: keep as-is, the doc is the contract. A human starts the worker, so the tool never held a handle to signal
