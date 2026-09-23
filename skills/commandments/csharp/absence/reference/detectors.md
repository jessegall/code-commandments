# C# absence — decide "missing" where the value is born — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-blank-string-default`** — a `string` parameter or property defaulted to `""` and then checked with `== ""` or `string.IsNullOrEmpty` — the blank is being used to mean "missing" — `BlankStringDefaultDetector`
- **`csharp-cancelled-coalesce`** — a `??` fallback compared against the same value it falls back to — `(name ?? "") != ""` — so "missing" and "empty" end up in one branch without saying so — `CancelledCoalesceDetector`
- **`csharp-invented-default`** — `F(x ?? "")` — an empty string, `0` or `false` invented to fill an argument, or answered by a lookup helper on a miss, a stand-in the callee cannot tell from real data — `InventedDefaultDetector`
- **`csharp-null-forgiven`** — The null-forgiving `!` on a value declared nullable — the compiler told the caller it may be null, and `!` silences it instead of deciding — `NullForgivenDetector`
