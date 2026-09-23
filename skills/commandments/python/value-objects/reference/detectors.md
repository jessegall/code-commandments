# Python value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-data-clump`** — The same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept wearing no name — `DataClumpDetector`
- **`python-dict-bag`** — A parameter typed as a dict read by string keys — `row["sku"]`, `row.get("quantity")` — a record nobody declared — `DictBagDetector`
- **`python-dict-return-bag`** — `return {"total": …, "tax": …}` — a record of several fields handed back as a dict its callers read by string key — `DictReturnBagDetector`
