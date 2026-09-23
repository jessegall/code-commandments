# C# duplication — one behaviour, one home — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`duplicate-csharp-method`** — Copy-pasted code — two+ C# methods, accessors or local functions with an identical body, formatting, comments and attributes aside — `DuplicateMethodDetector`
- **`near-duplicate-csharp-method`** — A near-copy — two+ C# methods, accessors or local functions with one control-flow skeleton that differ only in their local names or the literals they use (a key, a route, a message) — `NearDuplicateMethodDetector`
