# Go flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`deep-go-nesting`** — An `if`, `for`, `switch` or `select` opening a fourth level of choices inside one Go function — a decision buried inside a decision — `DeepNestingDetector`
- **`go-loop-wrapped-in-if`** — A `for` whose whole body is one `if`, burying the work a level deep where `continue` past what does not apply would keep it flat — `LoopWrappedInIfDetector`
- **`redundant-go-else`** — An `else` after an `if` branch that already left — it ends in `return`, `break`, `continue`, `goto` or `panic` — indenting the rest of the function for nothing — `RedundantElseDetector`
