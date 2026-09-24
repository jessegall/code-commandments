# C# value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-array-return-bag`** — a method that returns `new Dictionary<string, object> { ["sku"] = …, ["qty"] = … }` — a record with fixed fields, handed back as a dictionary — `ArrayReturnBagDetector`
- **`csharp-coupled-fields`** — a type whose own fields always travel together — assembled into one value again and again, null-checked together, or one copying what a sibling field already holds — one concept held as several fields — `CoupledFieldsDetector`
- **`csharp-data-clump`** — The same three or more string, number, date or id parameters threaded through methods of two or more types — values that always travel together but have no type of their own. — `DataClumpDetector`
- **`csharp-dictionary-bag`** — A string-keyed dictionary or JSON object read by keys written in the source — `row["sku"]`, `json.GetProperty("name")` — a record nobody declared — `DictionaryBagDetector`
- **`csharp-mutable-value-object`** — a record that can change after it is built — a `set` accessor, or a method that writes its own state — so two holders of the same value can end up seeing different things — `MutableValueObjectDetector`
- **`csharp-positional-tuple-return`** — a method that returns `(decimal, decimal, string)` — unnamed values the caller reads by position, where two of the same type can be swapped and nothing notices — `PositionalTupleReturnDetector`
