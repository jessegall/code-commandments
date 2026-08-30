# fix manufactured-fake-fill at Task.php:200

On main since the tasks lane merged; the worker was stopped mid round-2 before committing a fix. A required slot filled with a manufactured empty — fix at the source (Option or a named failure), not by swapping one empty literal for another.

- queued 2026-08-30 16:28
- started 2026-08-30 16:54
