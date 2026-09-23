# Python repeated call helper — name what you keep spelling out — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-repeated-guard`** — the SAME compound `and` condition recurs in 2+ places — reordered or read through a local still counts — a question with no name — `RepeatedGuardDetector`
- **`python-repeated-named-call`** — the same `**changes` function is called with the same keyword, built the same way, at 2+ sites — an operation the type never named — `RepeatedNamedCallDetector`
