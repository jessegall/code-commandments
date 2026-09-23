# C# absence — decide "missing" where the value is born — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-invented-default`** — `F(x ?? "")` — an empty string, `0` or `false` invented to fill an argument, or answered by a lookup helper on a miss, a stand-in the callee cannot tell from real data — `InventedDefaultDetector`
