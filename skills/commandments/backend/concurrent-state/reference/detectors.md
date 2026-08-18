# Concurrent state — a plain object behind `::for()` — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`concurrent-subclass`** — Class `extends Concurrent` instead of composing `Concurrent<self>` — `ConcurrentSubclassDetector`
