# Tell, don't ask — behaviour belongs with its data — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`feature-envy`** — Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object — `FeatureEnvyDetector`
- **`keyed-lookup-envy`** — Indirect feature envy — a method that uses an owned object's IDENTITY as a key to look up a fact about it through a collaborator — `KeyedLookupEnvyDetector`
- **`type-switch`** — two or more `instanceof` tests on the same subject deciding different branches — asking a value what it IS instead of telling it what to do — `TypeSwitchDetector`
