# a hand-deleted worktree is a lane kill cannot free

MEASURED (story 16): recovery needs raw git worktree prune plus git branch -D, which is exactly the raw-git fallback SPEC 8 calls a bug. Either kill handles a lane whose worktree is gone, or there is a verb for it.

- queued 2026-08-30 17:54
