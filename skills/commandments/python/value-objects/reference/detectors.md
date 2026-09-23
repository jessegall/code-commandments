# Python value objects — give related data a type — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-coupled-fields`** — a class whose own fields always travel together — assembled into one value again and again, guarded together, or one mirroring a sibling's — one concept held as several fields — `CoupledFieldsDetector`
- **`python-data-clump`** — The same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept wearing no name — `DataClumpDetector`
- **`python-dict-bag`** — A parameter typed as a dict read by string keys — `row["sku"]`, `row.get("quantity")` — a record nobody declared — `DictBagDetector`
- **`python-dict-return-bag`** — `return {"total": …, "tax": …}` — a record of several fields handed back as a dict its callers read by string key — `DictReturnBagDetector`
- **`python-hand-rolled-replace`** — `return Order(self.number, self.lines, self.note, "paid")` in a dataclass — every field re-listed to change one — `HandRolledReplaceDetector`
- **`python-mutable-value-object`** — a dataclass whose own methods write the fields it was built from after construction — a value that changes under everyone holding it — `MutableValueObjectDetector`
- **`python-positional-tuple-return`** — `return net, vat, currency` — a bundle of different things the caller must unpack by position, where a reordering breaks silently — `PositionalTupleReturnDetector`
- **`python-raw-decoded-return`** — `return json.loads(…)` — decoded text from outside handed on as bare dicts and lists, its shape known to no type — `RawDecodedReturnDetector`
