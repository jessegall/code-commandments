# C# flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-inline-throw`** — a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work — `csharp-inline-throw`
