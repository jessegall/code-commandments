# C# fix at the source — fix a value where it is made, not where it breaks — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-constructor-side-effect`** — a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it — `ConstructorSideEffectDetector`
