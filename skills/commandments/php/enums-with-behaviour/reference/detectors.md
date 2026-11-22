# Enums with behaviour — seal the set, put the logic on the type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`compare-self`** — A strict comparison bypasses an enum's verified comparison method. — `compare-self`
- **`enum-value-match`** — A `match` or `switch` over an enum's backing value at the call site: logic that belongs on the enum but lives elsewhere instead. — `enum-value-match`
