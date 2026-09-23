# Python absence — decide "missing" where the value is born — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-blank-string-default`** — `x: str = ""` standing in for absence — then asked `x == ""`, `not x` or `if x:` in its own scope — `BlankStringDefaultDetector`
- **`python-invented-default`** — `f(x or "")` — an empty string, `0` or `False` invented to fill an argument when the value is missing, a stand-in the callee cannot tell from real data — `InventedDefaultDetector`
