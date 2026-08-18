# Role vocabulary — the name is the contract — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`nullable-registry-lookup`** — A keyed-store `get()` that returns `null` on a miss (should resolve-or-throw) — `NullableRegistryLookupDetector`
