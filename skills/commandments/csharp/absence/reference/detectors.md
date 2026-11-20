# C# absence — decide "missing" where the value is born — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-null-forgiven`** — The null-forgiving `!` on a value declared nullable — the compiler told the caller it may be null, and `!` silences it instead of deciding — `NullForgivenDetector`
