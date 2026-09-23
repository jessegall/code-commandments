# Python enums — seal the set, put the knowledge on the case — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-constant-class-enum`** — a class that is nothing but `PENDING = "pending"` constants — a closed set of values written out by hand instead of an `Enum` — `ConstantClassEnumDetector`
- **`python-enum-case-or-chain`** — `s == Status.PENDING or s == Status.LATE` — a group of an enum's members re-derived at the call site instead of named on the enum — `EnumCaseOrChainDetector`
- **`python-enum-value-match`** — `match status.value: case "paid": …` at a call site — the enum's raw values matched again where the enum could answer — `EnumValueMatchDetector`
- **`python-in-literals-mirrors-enum`** — `x in ("pending", "late")` whose literals are an existing enum's values — a group of its members spelled as raw strings at the call site — `InLiteralsMirrorsEnumDetector`
