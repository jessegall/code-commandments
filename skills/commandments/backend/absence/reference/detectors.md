# Absence — model "might not be there" honestly — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`blank-string-default`** — `string $x = ''` standing in for absence — then asked `$x === ''` — `BlankStringDefaultDetector`
- **`blank-string-on-the-wire`** — A `string` field sent over the wire whose TypeScript reader has to check `=== ''` to mean "missing" — only that reader knows the blank stands for absence. — `BlankStringOnTheWireDetector`
- **`cancelled-coalesce`** — `??` cancelled by the comparison it sits in — `($x ?? '') !== ''` — `CancelledCoalesceDetector`
- **`conditional-array-spread`** — An array built by spreading a conditional element — `...($x ? ['k' => $x] : [])` or `array_merge($base, $cond ? [...] : [])` — a ternary-and-empty-array trick that really just means "include this when the value is present." — `ConditionalArraySpreadDetector`
- **`de-nulled-finder`** — A finder that returns `null` for both "missing" and "broken" instead of throwing — the kind of `?T` finder whose callers all end up de-nulling it. — `DeNulledFinderDetector`
- **`erased-null-object`** — A blank-rendering Null Object written into a `string` slot — coerced back to `''` — `ErasedNullObjectDetector`
- **`nullable-callback`** — Nullable callback normalised in the body instead of a Null Object default — `NullableCallbackDetector`
- **`option-as-nullable`** — `Option<T>` used as if it were nullable — `?Option`, `Option | null`, `unwrapOr(null)` — `OptionAsNullableDetector`
