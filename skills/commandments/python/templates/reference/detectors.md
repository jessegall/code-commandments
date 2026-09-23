# Python templates — state the shape, don't assemble it — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-assembled-template`** — a multi-line string built as a list of line fragments and `"\n".join(...)`-ed, instead of a triple-quoted f-string that shows its output — `AssembledTemplateDetector`
