# C# type honesty — a type must say what is true — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-filled-parameter`** — a method returning nothing that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed — `FilledParameterDetector`
- **`csharp-placeholder-filled-data`** — `new Card(title, "")` — a record's required `string` filled with a blank so the record can be built, hiding a missing value no type check can see — `PlaceholderFilledDataDetector`
