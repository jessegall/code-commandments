# Fix at the source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`constructor-side-effect`** — A constructor that performs a side effect on a collaborator and throws away the result, so simply creating the object changes something outside it. — `ConstructorSideEffectDetector`
- **`divergent-twin`** — Two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other. — `DivergentTwinDetector`
- **`duplicate-function`** — Copy-pasted code — two+ functions with an identical AST (formatting/comments aside) — `DuplicateFunctionDetector`
- **`manufactured-fake-fill`** — `?? <empty literal>` filling a required slot (manufactured fake) — `ManufacturedFakeFillDetector`
- **`mutable-static-state`** — A write to a static property — really a global variable with a namespace attached — where whichever write happens last wins, so the order code runs in changes the result. — `MutableStaticStateDetector`
- **`near-duplicate-function`** — Redundant methods — two+ functions with the same SHAPE differing only in names/literals (type-2 clone) — `NearDuplicateFunctionDetector`
