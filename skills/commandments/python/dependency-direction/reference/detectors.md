# Python dependency direction — imports point down the stack — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-namespace-cycle`** — two of the project's packages import each other — a cycle that makes them one package wearing two names — `NamespaceCycleDetector`
