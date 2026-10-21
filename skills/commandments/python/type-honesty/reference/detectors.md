# Python type honesty — the annotation must not lie — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constant-property`** — an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one. — `ConstantPropertyDetector`
- **`python-filled-parameter`** — a def returning `None` that writes an attribute of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed — `FilledParameterDetector`
- **`python-impossible-guard`** — `if order is None` where `order` is annotated as a class with no `| None` — one of the two is wrong, and both are worth knowing — `ImpossibleGuardDetector`
- **`python-masked-invariant`** — a literal answering for the object's own scratch state — `self.period.includes(day) if self.period else False` — where the field is only unset because an operation sets it part-way — `MaskedInvariantDetector`
- **`python-phantom-nullable`** — a field annotated `X | None` that every read assumes is there and none guards — a `None` the design never has — `PhantomNullableDetector`
- **`python-placeholder-filled-data`** — `Card(title=…, body="")` — a dataclass field required as `str` handed the blank to satisfy the signature, a value the type cannot catch — `PlaceholderFilledDataDetector`
- **`python-scratch-state-restore`** — `previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call — `ScratchStateRestoreDetector`
