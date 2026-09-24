# C# tell, don't ask — behaviour lives with its data — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-type-switch`** — `shape switch { Circle c => …, Square s => … }` — asking which of your own types a value is, to decide what to do with it — `TypeSwitchDetector`
