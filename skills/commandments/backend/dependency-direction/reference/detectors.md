# Dependency direction — a layer may only reach DOWN — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`namespace-cycle`** — two namespaces that reference each other — neither can be read, tested or moved alone — `NamespaceCycleDetector`
- **`namespace-dependency`** — a declared layer references a layer it may not use (the arrow points back up) — `NamespaceDependencyDetector`
