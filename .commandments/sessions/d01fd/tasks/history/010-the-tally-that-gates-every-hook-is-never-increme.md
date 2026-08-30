# the tally that gates every hook is never incremented

MEASURED by storybook 2 (story 14): nothing in the engine ever runs state_incr tally tool_use, so the documented every-10th/25th cadence is fiction — both hooks fire on every backoff window from tool call one, gated only by a timer. The ticker does not tick. Wire the increment to a real per-tool-use event, and prove the cadence by running it, not by reading it.

- queued 2026-08-30 17:54
- done 2026-08-30 18:08 — a hook declares counts=<key>; the runner increments before evaluating; measured firing on 10 and 20 only, and stop does not count the tally it reads
