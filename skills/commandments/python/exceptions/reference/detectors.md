# Python exceptions — fail loud, named, at the source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-swallowed-exception`** — A bare `except:` or `except Exception` whose body only passes, continues or returns nothing — every failure, expected or not, made to vanish — `SwallowedExceptionDetector`
