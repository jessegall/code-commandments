# Enums — a closed set is a type that knows its cases — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`compare-self`** — A strict comparison bypasses an enum's verified comparison method. — `compare-self`
- **`const-class-enum`** — A class of constants defines a closed set of cases — `const-class-enum`
- **`enum-case-or-chain`** — An or-chain asks about several cases of one enum — `enum-case-or-chain`
- **`enum-value-match`** — A `match` or `switch` over an enum's backing value at the call site: logic that belongs on the enum but lives elsewhere instead. — `enum-value-match`
- **`in-array-mirrors-enum`** — A literal membership check repeats declared enum values — `in-array-mirrors-enum`
- **`side-table-trait`** — A separate list repeats which cases have a trait — `side-table-trait`
- **`string-match-mirrors-enum`** — A switch repeats literal values already declared as cases — `string-match-mirrors-enum`
- **`type-switch`** — Dispatch branches test the same value against several concrete types — `type-switch`
