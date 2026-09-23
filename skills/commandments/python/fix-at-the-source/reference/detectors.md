# Python fix at the source — where a value is born, not where it hurts — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constructor-side-effect`** — an `__init__` that tells a collaborator to act and throws the answer away — merely building the object changes the world — `ConstructorSideEffectDetector`
