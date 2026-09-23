# Python tell, don't ask — behaviour lives with its data — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-feature-envy`** — a method that loops another object's collection or writes its fields, reaching into it more than into its own state — behaviour exiled from the object it works on — `FeatureEnvyDetector`
- **`python-keyed-lookup-envy`** — a method that uses an object's key to fetch a fact about it through a collaborator — `self.registry.get(node.key).reserved` — treating the object as a key into its own data — `KeyedLookupEnvyDetector`
- **`python-type-switch`** — an `isinstance` ladder over classes the codebase owns — the value is asked what it is so the caller can decide what to do. — `TypeSwitchDetector`
