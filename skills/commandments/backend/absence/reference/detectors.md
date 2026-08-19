# Absence — model "might not be there" honestly — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`blank-string-default`** — `string $x = ''` standing in for absence — then asked `$x === ''` — `BlankStringDefaultDetector`
- **`blank-string-on-the-wire`** — a total `string` field whose TypeScript reader — holding this very type — asks it `=== ''`: the blank means "missing", and only the far side says so — `BlankStringOnTheWireDetector`
- **`cancelled-coalesce`** — `??` cancelled by the comparison it sits in — `($x ?? '') !== ''` — `CancelledCoalesceDetector`
- **`conditional-array-spread`** — An array is built by spreading a conditional element — `...($x ? ['k' => $x] : [])` / `array_merge($base, $cond ? [...] : [])` — the ternary-into-empty-array noise that hides 'include when present' — `ConditionalArraySpreadDetector`
- **`de-nulled-finder`** — Missing = broken state returned as `?T`/null instead of throwing (a `?T` finder whose callers de-null it) — `DeNulledFinderDetector`
- **`erased-null-object`** — A blank-rendering Null Object written into a `string` slot — coerced back to `''` — `ErasedNullObjectDetector`
- **`nullable-callback`** — Nullable callback normalised in the body instead of a Null Object default — `NullableCallbackDetector`
- **`option-as-nullable`** — `Option<T>` used as a nullable costume — `?Option`, `Option | null`, `unwrapOr(null)` — `OptionAsNullableDetector`
