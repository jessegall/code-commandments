# Python pass the object — demand what you use, not an id and its container — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-computed-boolean-argument`** — a method taking only bools that every caller computes from the same object — the decision re-derived at each call site — `ComputedBooleanArgumentDetector`
- **`python-converted-argument`** — a scalar parameter its callers keep filling with the same conversion — `receipt_for(str(order.id))` call after call — because it asks for the converted form instead of the value — `ConvertedArgumentDetector`
