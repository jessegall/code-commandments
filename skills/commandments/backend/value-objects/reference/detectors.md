# Value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`array-bag`** — String-indexing (`$arr['key']`) a structured array param instead of giving it a name — the type was never defined. — `ArrayBagDetector`
- **`array-return-bag`** — Returning a multi-field string-keyed array literal (a bag that should be a value object) — `ArrayReturnBagDetector`
- **`coupled-fields`** — A class's own fields always change and get checked together — one concept split across several fields — and should be folded into a single value object. — `CoupledFieldsDetector`
- **`data-clump`** — The same 3+ scalar params threaded through 2+ classes (a recurring data clump → one object) — `DataClumpDetector`
- **`hand-rolled-wither`** — A wither method rebuilds the whole object by re-listing every constructor field, so adding a new field means updating every wither in the class. — `HandRolledWitherDetector`
- **`mutable-value-object`** — A value type that mutates its own field after construction, so two things holding what should be the same value can end up different — one changes without the other knowing. — `MutableValueObjectDetector`
- **`positional-tuple-return`** — Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` — bundling independent values as a keyless list the caller destructures by position — `PositionalTupleReturnDetector`
- **`raw-decoded-array-return`** — Returning a raw decoded boundary array (`json_decode(...)`) untyped — `RawDecodedArrayReturnDetector`
- **`flat-field-cluster`** — A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of nesting the existing `Wire{type, label}`. — `FlatFieldClusterDetector`
