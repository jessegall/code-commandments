# One method, one behaviour — never a flag that picks — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`constant-argument`** — Every caller of one of the project's methods passes the same constant for one parameter, so the parameter is not a choice — `constant-argument`
- **`flag-argument`** — a method whose whole body branches on a `bool` parameter — or on whether a nullable one was given — two methods sharing one name — `flag-argument`
