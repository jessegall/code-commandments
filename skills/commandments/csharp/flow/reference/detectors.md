# C# flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`deep-csharp-nesting`** — An `if`, loop or `switch` opening a fourth level of choices inside one C# method — an arrow of conditions and loops — `DeepNestingDetector`
