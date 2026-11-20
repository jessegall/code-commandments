# Enums with behaviour — seal the set, put the logic on the type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`enum-value-match`** — A `match`/`switch` over an enum's `->value` at the call site — logic that belongs on the enum but lives elsewhere instead. — `EnumValueMatchDetector`
