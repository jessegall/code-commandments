# Method mood — an order, or a question — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`bare-state-predicate`** — A `bool` about the object's own state named as a bare verb — `binds()`, `spins()` — where a question belongs — `BareStatePredicateDetector`
- **`narrated-command`** — A command named in the third person — `hides()`, `entersTestMode()` — where a call is an order, not a description of one — `NarratedCommandDetector`
