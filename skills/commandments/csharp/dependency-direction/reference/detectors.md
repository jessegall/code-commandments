# C# dependency direction — references point down the stack — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-namespace-cycle`** — two of the project's namespaces that each use the other — a cycle that makes them one namespace split under two names — `NamespaceCycleDetector`
- **`csharp-namespace-dependency`** — a reference out of a declared layer into a namespace that layer did not declare it may use — `NamespaceDependencyDetector`
