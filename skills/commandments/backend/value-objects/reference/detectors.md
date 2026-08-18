# Value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`array-bag`** — String-indexing (`$arr['key']`) a structured array param (an unborn type) — `ArrayBagDetector`
- **`array-return-bag`** — Returning a multi-field string-keyed array literal (a bag that should be a value object) — `ArrayReturnBagDetector`
- **`coupled-fields`** — A class's own fields always travel together — one concept masquerading as several fields, guards, and reaches — and should be a single value object — `CoupledFieldsDetector`
- **`data-clump`** — The same 3+ scalar params threaded through 2+ classes (a recurring data clump → one object) — `DataClumpDetector`
- **`hand-rolled-wither`** — A wither rebuilds its object by re-spelling every constructor field, so each new field must be threaded through N of them — `HandRolledWitherDetector`
- **`mutable-value-object`** — a value type that writes its own field after construction — two holders of the same value, and one of them can change it under the other — `MutableValueObjectDetector`
- **`positional-tuple-return`** — Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` — bundling independent values as a keyless list the caller destructures by position — `PositionalTupleReturnDetector`
- **`raw-decoded-array-return`** — Returning a raw decoded boundary array (`json_decode(...)`) untyped — `RawDecodedArrayReturnDetector`
- **`flat-field-cluster`** — A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of NESTING the existing `Wire{type, label}` — width instead of depth — `FlatFieldClusterDetector`
