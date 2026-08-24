# Fix at the source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`constructor-side-effect`** — a constructor that performs a SIDE EFFECT on a collaborator — the result thrown away, so merely building the object changes the world — `ConstructorSideEffectDetector`
- **`divergent-twin`** — Two methods do one job — the same rare verbs, in different words — and one of them does strictly less of it, which is what a change looks like when it landed in only one of the two places that should have been one — `DivergentTwinDetector`
- **`duplicate-function`** — Copy-pasted code — two+ functions with an identical AST (formatting/comments aside) — `DuplicateFunctionDetector`
- **`manufactured-fake-fill`** — `?? <empty literal>` filling a required slot (manufactured fake) — `ManufacturedFakeFillDetector`
- **`mutable-static-state`** — a write to a static property — a global wearing a namespace, where whoever writes last wins and execution order becomes load-bearing — `MutableStaticStateDetector`
- **`near-duplicate-function`** — Redundant methods — two+ functions with the same SHAPE differing only in names/literals (type-2 clone) — `NearDuplicateFunctionDetector`
