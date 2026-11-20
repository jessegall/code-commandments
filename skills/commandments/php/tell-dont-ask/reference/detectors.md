# Tell, don't ask — behaviour belongs with its data — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`feature-envy`** — Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object — `FeatureEnvyDetector`
- **`guard-beside-transition`** — A caller checks an object's state against an enum case and throws, then tells that object to change: the rule sits outside the transition it guards — `GuardBesideTransitionDetector`
- **`keyed-lookup-envy`** — Indirect feature envy — a method that uses an owned object's IDENTITY as a key to look up a fact about it through a collaborator — `KeyedLookupEnvyDetector`
- **`middle-man`** — A class whose every public method only hands its arguments, unchanged, to the same field: it adds nothing between the caller and the object that does the work. — `MiddleManDetector`
- **`train-wreck`** — `$order->customer()->address()->city()` — a chain crossing three of the project's own types, so a change three objects away reaches this caller — `TrainWreckDetector`
