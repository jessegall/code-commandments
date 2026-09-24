# C# behaviour per method — one method, one job — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-flag-argument`** — a method whose whole body branches on a `bool` parameter — `if (compact) … else …` — two methods sharing one name — `FlagArgumentDetector`
