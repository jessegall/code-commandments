# C# type honesty — a type must say what is true — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-placeholder-filled-data`** — `new Card(title, "")` — a record's required `string` filled with a blank so the record can be built, hiding a missing value no type check can see — `PlaceholderFilledDataDetector`
