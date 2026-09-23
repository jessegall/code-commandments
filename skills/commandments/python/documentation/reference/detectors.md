# Python documentation — concise, present-tense, rare — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-archaeology-comment`** — a comment or docstring narrating the code's history — where it lived, what it replaced, what it no longer is — `ArchaeologyCommentDetector`
