# Flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`deep-nesting`** — A decision sits deep inside other decisions in one function — `DeepNestingDetector`
- **`duplicated-branch-tail`** — Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes. — `DuplicatedBranchTailDetector`
- **`loop-inverted-guard`** — A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition — `LoopInvertedGuardDetector`
- **`nested-ternary`** — A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression — `NestedTernaryDetector`
- **`non-counting-for`** — A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count — `NonCountingForDetector`
- **`query-in-loop`** — A database query runs on each iteration — `QueryInLoopDetector`
- **`reassigned-parameter`** — A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one. — `ReassignedParameterDetector`
- **`redundant-else`** — An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing — `RedundantElseDetector`
- **`short-circuit-statement`** — A bare `a && b()` or `a || b()` statement: a short-circuit whose value nothing reads, so the operator is an `if` in disguise — `ShortCircuitStatementDetector`
- **`subject-ladder`** — An `if`/`else if` chain of four or more rungs that each test the same subject for equality with a constant: a dispatch written as a ladder — `SubjectLadderDetector`
- **`ternary-statement`** — A bare conditional expression as a statement (`cond ? doThis() : doThat()`): its value is never read, so it chooses an action, not a value — `TernaryStatementDetector`
- **`unnamed-condition`** — A condition of three or more parts over two or more objects whose decision has no name — `UnnamedConditionDetector`
- **`unreachable-statement`** — A statement after a return, throw, break or continue in the same block: it can never run. — `UnreachableStatementDetector`
