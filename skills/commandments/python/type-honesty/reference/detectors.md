# Python type honesty — the annotation must not lie — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constant-property`** — an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one. — `ConstantPropertyDetector`
- **`python-masked-invariant`** — a literal answering for the object's own scratch state — `self.period.includes(day) if self.period else False` — where the field is only unset because an operation sets it part-way — `MaskedInvariantDetector`
- **`python-phantom-nullable`** — a field annotated `X | None` that every read assumes is there and none guards — a `None` the design never has — `PhantomNullableDetector`
- **`python-placeholder-filled-data`** — `Card(title=…, body="")` — a dataclass field required as `str` handed the blank to satisfy the signature, a value the type cannot catch — `PlaceholderFilledDataDetector`
- **`python-scratch-state-restore`** — `previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call — `ScratchStateRestoreDetector`
