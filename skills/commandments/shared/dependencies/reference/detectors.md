# Dependencies — point one way, through contracts at the boundary — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`concrete-cross-module-dependency`** — A constructor takes another module's concrete service — `concrete-cross-module-dependency`
- **`interface-overlap`** — An interface repeats the signatures of a contract it can reuse. — `interface-overlap`
- **`lone-implementer-interface`** — An interface has only one local implementation — `lone-implementer-interface`
- **`namespace-cycle`** — Two independent modules import each other — `namespace-cycle`
- **`namespace-dependency`** — a declared layer references a layer it may not use (the arrow points back up) — `namespace-dependency`
- **`undeclared-member`** — A member access the receiver's declared type neither declares nor inherits — `undeclared-member`
