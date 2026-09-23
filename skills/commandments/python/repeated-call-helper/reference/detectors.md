# Python repeated call helper — name what you keep spelling out — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-repeated-guard`** — the same compound `and` condition recurs in 2+ places — it still counts even when reordered, or read through a local variable — and nobody has named it. — `RepeatedGuardDetector`
- **`python-repeated-named-call`** — the same `**changes` call is built the same way with the same keyword at 2+ sites — an operation that has no name on the type it belongs to. — `RepeatedNamedCallDetector`
- **`python-repeated-type-guard`** — the same multi-`isinstance` narrowing (`isinstance(x, A) and isinstance(x.y, B)`) is written in 2+ places — a check on a shape that nobody has named. — `RepeatedTypeGuardDetector`
