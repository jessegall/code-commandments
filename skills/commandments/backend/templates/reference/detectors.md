# Templates — state the shape, don't assemble it — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`assembled-template`** — A multi-line template assembled as an array of line fragments and joined with a newline — the output is unreadable in the source that emits it — `AssembledTemplateDetector`
