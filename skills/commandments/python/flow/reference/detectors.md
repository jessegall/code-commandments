# Python flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-coalesced-loop-subject`** — `for x in d.get(k, [])` / `for x in y or []` over a parameter — whether the caller handed anything over, decided in the loop header instead of stated as a guard — `CoalescedLoopSubjectDetector`
- **`python-conditional-statement`** — a bare `a() if x else b()` statement — a conditional expression whose value nothing reads, so it chooses an action, not a value. — `ConditionalStatementDetector`
- **`deep-python-nesting`** — An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops — `DeepNestingDetector`
- **`python-loop-wrapped-in-if`** — A `for` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition — `LoopWrappedInIfDetector`
- **`python-nested-conditional`** — `a if x else b if y else c` — a conditional expression inside another's branch, a branching decision folded into one line — `NestedConditionalDetector`
- **`redundant-python-else`** — An `else:` after an `if` branch that already left — it ends in `return`, `raise`, `continue` or `break` — indenting the rest of the function for nothing — `RedundantElseDetector`
- **`python-short-circuit-statement`** — a bare `a and b()` or `a or b()` statement — an `and`/`or` whose value nothing reads, so the operator is really acting as an `if`. — `ShortCircuitStatementDetector`
- **`python-subject-ladder`** — An `if`/`elif` chain of four or more rungs that each test the same subject for equality with a constant — a dispatch written as a ladder. — `SubjectLadderDetector`
