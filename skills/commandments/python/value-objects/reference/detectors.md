# Python value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-dict-bag`** — A parameter typed as a dict read by string keys — `row["sku"]`, `row.get("quantity")` — a record nobody declared — `DictBagDetector`
