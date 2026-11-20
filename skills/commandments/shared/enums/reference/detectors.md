# Enums: give a closed set one source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`const-class-enum`** — A class of constants defines a closed set of cases — `ConstClassEnumDetector`
- **`enum-case-or-chain`** — An or-chain asks about several cases of one enum — `EnumCaseOrChainDetector`
- **`in-array-mirrors-enum`** — A literal membership check repeats declared enum values — `InArrayMirrorsEnumDetector`
- **`side-table-trait`** — A separate list repeats which cases have a trait — `SideTableTraitDetector`
- **`string-match-mirrors-enum`** — A switch repeats literal values already declared as cases — `StringMatchMirrorsEnumDetector`
- **`type-switch`** — Dispatch branches test the same value against several concrete types — `TypeSwitchDetector`
