# Python absence — decide "missing" where the value is born — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-blank-string-default`** — `x: str = ""` standing in for absence — then asked `x == ""`, `not x` or `if x:` in its own scope — `BlankStringDefaultDetector`
- **`python-cancelled-fallback`** — `(x or "") != ""` — a value defaulted to a blank only to be compared against that same blank, so absent and empty take one branch unnamed — `CancelledFallbackDetector`
- **`python-conditional-spread`** — `**({"k": v} if v else {})` / `*([x] if x else [])` — an entry spread in only when present, the absence decided in a conditional into an empty collection — `ConditionalSpreadDetector`
- **`python-invented-default`** — `f(x or "")` — an empty string, `0` or `False` invented to fill an argument when the value is missing, a stand-in the callee cannot tell from real data — `InventedDefaultDetector`
