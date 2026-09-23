# Python tell, don't ask — behaviour lives with its data — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-type-switch`** — an `isinstance` ladder over classes the codebase owns — the value asked what it IS so the caller can decide what to do — `TypeSwitchDetector`
