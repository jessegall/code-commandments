# Guard clauses & flow — check at the top, then go straight — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`if-else-ladder`** — if/elseif ladder of 4+ branches (should be match/dispatch) — `if-else-ladder`
- **`inline-throw`** — `?? throw` fed into a call or dereferenced on the same line (inline throw mid-expression) — `inline-throw`
