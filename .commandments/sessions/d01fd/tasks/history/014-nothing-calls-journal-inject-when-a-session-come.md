# nothing calls journal inject when a session comes back

MEASURED (story 20): inject is exactly as bounded and honest as specified, and nothing invokes it for a session returning from compaction — which is the single moment the journal exists for.

- queued 2026-08-30 17:54
- done 2026-08-30 18:10 — a pre-compact hook names journal inject
