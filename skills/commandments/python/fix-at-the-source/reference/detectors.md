# Python fix at the source — where a value is born, not where it hurts — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constructor-side-effect`** — an `__init__` that tells a collaborator to act and throws the answer away — merely building the object has an effect outside it. — `ConstructorSideEffectDetector`
- **`python-divergent-twin`** — two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other. — `DivergentTwinDetector`
- **`python-mutable-static-state`** — a `global` written from a function, or a class attribute set from a method — state no instance owns, changed by whoever ran last — `MutableStaticStateDetector`
