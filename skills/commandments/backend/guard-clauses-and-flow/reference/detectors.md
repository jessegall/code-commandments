# Guard clauses & flow — check at the top, then go straight — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`coalesced-loop-subject`** — `foreach ($x[$k] ?? [] as …)` — the absence check buried in the loop header instead of stated as a guard — `CoalescedLoopSubjectDetector`
- **`deep-nesting`** — `if` nested 3-deep (a pyramid — hoist guards / extract) — `DeepNestingDetector`
- **`if-else-ladder`** — if/elseif ladder of 4+ branches (should be match/dispatch) — `IfElseLadderDetector`
- **`inline-throw`** — `?? throw` fed into a call or dereferenced on the same line (inline throw mid-expression) — `InlineThrowDetector`
- **`loop-inverted-guard`** — Loop body (multi-statement) wrapped in an `if` instead of `continue` guard — `LoopInvertedGuardDetector`
- **`nested-ternary`** — Nested/chained ternary `$a ? $b : ($c ? $d : $e)` (hidden control flow) — `NestedTernaryDetector`
- **`non-counting-for`** — a `for` whose step assigns the next thing instead of advancing a counter — a walk wearing a counted loop's clothes — `NonCountingForDetector`
- **`redundant-else`** — `else` after an `if` branch that already returns/throws (redundant) — `RedundantElseDetector`
- **`short-circuit-statement`** — a bare `$a && $b->do();` statement — a short-circuit whose result nothing reads, so the operator is an `if` in disguise — `ShortCircuitStatementDetector`
- **`ternary-statement`** — a bare `$cond ? doThis() : doThat();` statement — a ternary whose value nothing reads, so it is choosing an ACTION, not a value — `TernaryStatementDetector`
