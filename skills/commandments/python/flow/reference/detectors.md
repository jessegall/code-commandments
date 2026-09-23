# Python flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`deep-python-nesting`** — An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops — `DeepNestingDetector`
- **`redundant-python-else`** — An `else:` after an `if` branch that already left — it ends in `return`, `raise`, `continue` or `break` — indenting the rest of the function for nothing — `RedundantElseDetector`
- **`python-subject-ladder`** — An `if`/`elif` chain of four or more rungs that each test ONE subject for equality with a constant — a dispatch written as a ladder — `SubjectLadderDetector`
