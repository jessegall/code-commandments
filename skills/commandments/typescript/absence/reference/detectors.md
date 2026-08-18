# TypeScript absence — say what is missing, and mean it — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`defended-certain-field`** — An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, which reads as doubt the design does not have — `DefendedCertainFieldDetector`
- **`falsely-optional-field`** — A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen — `FalselyOptionalFieldDetector`
