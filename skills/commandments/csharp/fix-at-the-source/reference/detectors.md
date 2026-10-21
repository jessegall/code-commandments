# C# fix at the source — fix a value where it is made, not where it breaks — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-blocked-await`** — `.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task — the thread it blocks is the one the work needed back, so it deadlocks under load and nowhere else — `BlockedAwaitDetector`
- **`csharp-constructor-side-effect`** — a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it — `ConstructorSideEffectDetector`
- **`csharp-mutable-static-state`** — a `static` field that methods write to — state no instance owns, changed by whichever code ran last — `MutableStaticStateDetector`
- **`csharp-unread-collaborator`** — a private readonly field, or a primary constructor parameter, that nothing in the type reads — the type asks for something it does not use — `UnreadCollaboratorDetector`
