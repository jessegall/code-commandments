# Spatie Data — feed the framework, don't hand-build — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`data-to-array-roundtrip`** — A `X::from(...)->toArray()` sits in a `::from` slot typed `X` that re-hydrates it — build → array → build — `DataToArrayRoundtripDetector`
- **`derived-collection-cast`** — A `#[DataCollectionOf]` is filled by mapping a factory over inputs at the call site, where a `#[WithCast]` should own the derivation — `DerivedCollectionShouldCastDetector`
- **`hand-key-remap`** — A `::from([...])` mechanically renames `$src['snake_key']` → `camelKey` by hand, instead of a class-level `#[MapInputName]` — `HandKeyRemapDetector`
- **`redundant-enum-unwrap`** — An enum is unwrapped to `->value` at a hydration site (`'status' => $order->status->value`) where the property is typed as that enum — Spatie re-casts the scalar straight back to the enum — `RedundantEnumUnwrapDetector`
- **`redundant-native-cast`** — An enum / date is constructed at a hydration site (`Enum::from($x)`, `new DateTime($x)`) where the property auto-casts the raw scalar — `RedundantNativeCastDetector`
- **`redundant-nested-from`** — A nested `X::from([...])` fills a slot the parent `::from` already auto-hydrates from the array — `RedundantNestedFromDetector`
