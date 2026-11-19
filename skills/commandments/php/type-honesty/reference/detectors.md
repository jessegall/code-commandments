# Type honesty — the type must not lie — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`filled-parameter`** — a `void` method that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed — `FilledParameterDetector`
- **`impossible-guard`** — `if ($order === null)` where `$order` is typed `Order`, with no `?` — one of the two is wrong, and both are worth knowing — `ImpossibleGuardDetector`
- **`masked-invariant`** — Masked invariant — an own field read as `?->… ?? <fake literal>`, even though the very operation sets that field first, so the fallback only ever answers an impossible "not set yet". — `MaskedInvariantDetector`
- **`phantom-nullable`** — Phantom nullable — a field typed `?T` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens — `PhantomNullableDetector`
- **`reassigned-parameter`** — A parameter overwritten inside its method, so one name holds the value the caller passed and later a different one. — `ReassignedParameterDetector`
- **`redundant-arrow-return-type`** — An arrow function whose return type only repeats what its one expression provably yields — `fn (): string => $this->name` on a `string` property — `RedundantArrowReturnTypeDetector`
- **`refused-bequest`** — A method that overrides or implements an inherited one only to throw: the class claims a contract it refuses to keep. — `RefusedBequestDetector`
- **`scratch-state-restore`** — Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input — `ScratchStateRestoreDetector`
- **`placeholder-filled-data`** — A required non-nullable `string` slot handed `''` — the type promises a value that is always there and the caller has none — `PlaceholderFilledDataDetector`
- **`unused-parameter`** — A parameter of a method no subclass can override that its body never reads: every caller must pass a value nothing uses. — `UnusedParameterDetector`
- **`unused-private-member`** — A private method nothing in its class calls, or a private property nothing in its class reads: dead code the class still carries. — `UnusedPrivateMemberDetector`
- **`useless-property-hook`** — A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax — `UselessPropertyHookDetector`
