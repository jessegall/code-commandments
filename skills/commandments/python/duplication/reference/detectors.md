# Python duplication — one behaviour, one home — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`duplicate-python-function`** — Copy-pasted code — two+ Python functions or methods with an identical body, formatting, comments and docstrings aside — `DuplicateFunctionDetector`
- **`near-duplicate-python-function`** — A near-copy — two+ Python functions or methods with one control-flow skeleton that differ only in their local names or the literals they use (a path, a key, a message) — `NearDuplicateFunctionDetector`
