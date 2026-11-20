# Absence — model "might not be there" honestly — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`blank-string-on-the-wire`** — A string sent over the wire that the frontend reads as absent when it is blank. — `BlankStringOnTheWireDetector`
- **`erased-null-object`** — A blank-rendering Null Object written into a `string` slot — coerced back to `''` — `ErasedNullObjectDetector`
- **`false-for-nothing`** — A return typed `X|false`: `false` stands in for "nothing" or "failed", and every caller must test for it. — `FalseForNothingDetector`
- **`option-as-nullable`** — `Option<T>` used as if it were nullable — `?Option`, `Option | null`, `unwrapOr(null)` — `OptionAsNullableDetector`
