# Value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`array-bag`** — String-indexing (`$arr['key']`) a structured array param instead of giving it a name — the type was never defined. — `array-bag`
- **`array-bag-parameter`** — A parameter declared `array` that callers fill with string-keyed records (`['mode' => …, 'plan' => …]`): the method never defined the type its fields belong to. — `array-bag-parameter`
- **`array-return-bag`** — Returning a multi-field string-keyed array literal (a bag that should be a value object) — `array-return-bag`
- **`coupled-fields`** — A class's own fields always change and get checked together — one concept split across several fields — and should be folded into a single value object. — `coupled-fields`
- **`flat-field-cluster`** — A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of nesting the existing `Wire{type, label}`. — `FlatFieldClusterDetector`
- **`hand-rolled-wither`** — A wither method rebuilds the whole object by re-listing every constructor field, so adding a new field means updating every wither in the class. — `hand-rolled-wither`
- **`mutable-value-object`** — A value type that mutates its own field after construction, so two things holding what should be the same value can end up different — one changes without the other knowing. — `mutable-value-object`
- **`positional-tuple-return`** — Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` — bundling independent values as a keyless list the caller destructures by position — `positional-tuple-return`
