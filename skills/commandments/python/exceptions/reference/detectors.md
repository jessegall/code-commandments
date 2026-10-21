# Python exceptions — fail loud, named, at the source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-message-string-raise`** — `raise Exception/RuntimeError("…")` — a failure that names nothing, described in prose at the raise site — `MessageStringRaiseDetector`
- **`python-raise-without-cause`** — `raise Other(...)` inside an `except` block with no `from` — the failure being handled left as an implicit context, never named as the cause — `RaiseWithoutCauseDetector`
- **`python-rethrown-unchanged`** — an `except` whose whole body is `raise` or `raise error` — the clause catches the failure, adds nothing to it and hands it back on, which is what writing no clause does — `RethrownUnchangedDetector`
- **`python-swallowed-exception`** — A bare `except:` or `except Exception` whose body only passes, continues or returns nothing — every failure, expected or not, made to vanish — `SwallowedExceptionDetector`
