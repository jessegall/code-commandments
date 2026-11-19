# Guard clauses & flow — check at the top, then go straight — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`coalesced-loop-subject`** — `foreach ($x[$k] ?? [] as …)` — the absence check buried in the loop header instead of stated as a guard — `CoalescedLoopSubjectDetector`
- **`deep-nesting`** — `if` nested 3-deep (a pyramid — hoist guards / extract) — `DeepNestingDetector`
- **`if-else-ladder`** — if/elseif ladder of 4+ branches (should be match/dispatch) — `IfElseLadderDetector`
- **`inline-throw`** — `?? throw` fed into a call or dereferenced on the same line (inline throw mid-expression) — `InlineThrowDetector`
- **`non-counting-for`** — A `for` loop that looks like it's counting, but its step actually assigns the next item instead of incrementing a counter. — `NonCountingForDetector`
- **`unnamed-condition`** — `if ($order->isPaid() && $customer->trusted && $slot->open)` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it — `UnnamedConditionDetector`
