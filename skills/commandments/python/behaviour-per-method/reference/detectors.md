# Python behaviour per method — never a flag that picks — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-flag-argument`** — a function whose whole body branches on a `bool` parameter — or on whether an optional one was given — two functions sharing one name — `FlagArgumentDetector`
