# Type honesty — the type must not lie — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`masked-invariant`** — Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible "not set yet" — `MaskedInvariantDetector`
- **`phantom-nullable`** — Phantom nullable — a field typed `?T` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens — `PhantomNullableDetector`
- **`redundant-arrow-return-type`** — An arrow function whose return type only repeats what its one expression provably yields — `fn (): string => $this->name` on a `string` property — `RedundantArrowReturnTypeDetector`
- **`scratch-state-restore`** — Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input — `ScratchStateRestoreDetector`
- **`placeholder-filled-data`** — A required non-nullable `string` slot handed `''` — the type promises a value that is always there and the caller has none — `PlaceholderFilledDataDetector`
- **`useless-property-hook`** — A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax — `UselessPropertyHookDetector`
