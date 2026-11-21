# Dependencies: keep imports going in one direction — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`concrete-cross-module-dependency`** — A constructor takes another module's concrete service — `concrete-cross-module-dependency`
- **`interface-overlap`** — An interface repeats the signatures of a contract it can reuse. — `InterfaceOverlapDetector`
- **`lone-implementer-interface`** — An interface has only one local implementation — `lone-implementer-interface`
- **`namespace-cycle`** — Two independent modules import each other — `namespace-cycle`
- **`undeclared-member`** — A member access the receiver's declared type neither declares nor inherits — `undeclared-member`
