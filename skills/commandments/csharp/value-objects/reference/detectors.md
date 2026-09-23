# C# value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-data-clump`** — The same three or more string, number, date or id parameters threaded through methods of two or more types — values that always travel together but have no type of their own. — `DataClumpDetector`
- **`csharp-dictionary-bag`** — A string-keyed dictionary or JSON object read by keys written in the source — `row["sku"]`, `json.GetProperty("name")` — a record nobody declared — `DictionaryBagDetector`
