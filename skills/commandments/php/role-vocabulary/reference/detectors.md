# Role vocabulary — the name is the contract — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`hand-rolled-registry`** — A class writes its own keyed store (fill under a key, throw on a missing key) instead of extending the one Registry base — `HandRolledRegistryDetector`
