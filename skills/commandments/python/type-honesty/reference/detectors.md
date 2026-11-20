# Python type honesty — the annotation must not lie — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constant-property`** — an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one. — `ConstantPropertyDetector`
- **`python-filled-parameter`** — a def returning `None` that writes an attribute of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed — `FilledParameterDetector`
- **`python-impossible-guard`** — `if order is None` where `order` is annotated as a class with no `| None` — one of the two is wrong, and both are worth knowing — `ImpossibleGuardDetector`
- **`python-scratch-state-restore`** — `previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call — `ScratchStateRestoreDetector`
