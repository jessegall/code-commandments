# Flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`collection-mutated-while-iterated`** — a `foreach` that adds to or removes from the very collection it walks — the enumerator reads a collection that moved under it, which throws InvalidOperationException — `collection-mutated-while-iterated`
- **`csharp-inline-throw`** — a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work — `csharp-inline-throw`
- **`deep-nesting`** — A decision sits deep inside other decisions in one function — `deep-nesting`
- **`duplicated-branch-tail`** — Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes. — `duplicated-branch-tail`
- **`if-else-ladder`** — if/elseif ladder of 4+ branches (should be match/dispatch) — `if-else-ladder`
- **`inline-throw`** — `?? throw` fed into a call or dereferenced on the same line (inline throw mid-expression) — `inline-throw`
- **`loop-inverted-guard`** — A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition — `loop-inverted-guard`
- **`nested-ternary`** — A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression — `nested-ternary`
- **`non-counting-for`** — A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count — `non-counting-for`
- **`query-in-loop`** — a query run inside a loop — one query per turn, so the work grows with the data while the code reads as though it were one call — `query-in-loop`
- **`reassigned-parameter`** — A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one. — `reassigned-parameter`
- **`redundant-else`** — An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing — `redundant-else`
- **`short-circuit-statement`** — A bare `a && b()` or `a || b()` statement: a short-circuit whose value nothing reads, so the operator is an `if` in disguise — `short-circuit-statement`
- **`subject-ladder`** — An `if`/`else if` chain of four or more rungs that each test the same subject for equality with a constant: a dispatch written as a ladder — `subject-ladder`
- **`ternary-statement`** — A bare conditional expression as a statement (`cond ? doThis() : doThat()`): its value is never read, so it chooses an action, not a value — `ternary-statement`
- **`unnamed-condition`** — A condition of three or more parts over two or more objects whose decision has no name — `unnamed-condition`
- **`unreachable-statement`** — A statement after one that never completes (a return, throw, break or continue, an if whose every arm leaves, a loop that never ends): it can never run. — `unreachable-statement`
