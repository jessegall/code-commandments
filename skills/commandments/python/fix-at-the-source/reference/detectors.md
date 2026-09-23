# Python fix at the source — where a value is born, not where it hurts — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constructor-side-effect`** — an `__init__` that tells a collaborator to act and throws the answer away — merely building the object changes the world — `ConstructorSideEffectDetector`
- **`python-divergent-twin`** — two functions do one job — the same rare outside calls, in different words — and one does strictly less of it, which is what a change looks like when it landed in only one of the two places that should have been one — `DivergentTwinDetector`
- **`python-mutable-static-state`** — a `global` written from a function, or a class attribute set from a method — state no instance owns, changed by whoever ran last — `MutableStaticStateDetector`
