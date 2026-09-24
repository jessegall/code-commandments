# C# repeated call helper — name what you keep writing — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-repeated-guard`** — the same compound condition — `order.Paid && !order.Cancelled` — written at two or more sites, a question with no name — `RepeatedGuardDetector`
