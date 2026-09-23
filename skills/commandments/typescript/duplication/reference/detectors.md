# TypeScript duplication — one behaviour, one home — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`duplicate-typescript-function`** — Copy-pasted code — two+ TypeScript functions (a `function`, a method, a `const` arrow; in a `.ts` module or a component's script) with an identical body, formatting and comments aside — `DuplicateFunctionDetector`
- **`near-duplicate-typescript-function`** — A near-copy — two+ TypeScript functions with one control-flow skeleton that differ only in their local names or the literals they use (an endpoint, a key, a label) — `NearDuplicateFunctionDetector`
