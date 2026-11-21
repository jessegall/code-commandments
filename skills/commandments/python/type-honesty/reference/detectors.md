# Python type honesty — the annotation must not lie — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constant-property`** — an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one. — `python-constant-property`
