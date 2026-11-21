# C# fix at the source — fix a value where it is made, not where it breaks — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-blocked-await`** — `.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task — the thread it blocks is the one the work needed back, so it deadlocks under load and nowhere else — `csharp-blocked-await`
