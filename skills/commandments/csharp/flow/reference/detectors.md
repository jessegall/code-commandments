# C# flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-coalesced-loop-subject`** — a `foreach` over `items ?? []` (or `Enumerable.Empty<T>()`, or a new empty list) — the check for a missing collection is hidden in the loop header — `CoalescedLoopSubjectDetector`
- **`deep-csharp-nesting`** — An `if`, loop or `switch` opening a fourth level of choices inside one C# method — an arrow of conditions and loops — `DeepNestingDetector`
- **`csharp-inline-throw`** — a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work — `InlineThrowDetector`
- **`csharp-loop-wrapped-in-if`** — A `for`, `foreach` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition — `LoopWrappedInIfDetector`
- **`csharp-nested-ternary`** — a `?:` with another `?:` as one of its branches — several decisions packed into one expression — `NestedTernaryDetector`
- **`csharp-non-counting-for`** — a `for` loop whose step assigns the next item instead of moving a counter — a walk written as a count — `NonCountingForDetector`
- **`redundant-csharp-else`** — An `else` after an `if` branch that already left — it ends in `return`, `throw`, `continue` or `break` — indenting the rest of the method for nothing — `RedundantElseDetector`
- **`csharp-subject-ladder`** — An `if`/`else if` chain of four or more rungs that each compare the same subject with a constant — a dispatch written as a ladder. — `SubjectLadderDetector`
