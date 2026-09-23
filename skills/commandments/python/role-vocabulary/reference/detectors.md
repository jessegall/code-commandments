# Python role vocabulary — a Registry, a Set, a Resolver, and the contract each name promises — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-nullable-registry-lookup`** — a keyed store handing back `None` for a key it lacks — `return self._handlers.get(kind)` — so every caller decides what a miss means — `NullableRegistryLookupDetector`
