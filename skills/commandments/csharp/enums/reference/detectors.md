# C# enums — a closed set is a type, with its knowledge on it — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-string-mirrors-enum`** — A `switch` or an `if` ladder dispatching on strings that are the names of an enum the codebase already declares — the enum, written out again as text — `StringMirrorsEnumDetector`
