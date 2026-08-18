# Vue components — extract repetition and deep reaches — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`compound-inline-component`** — A compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`…) assembled INLINE with a substantial body — extract it into its own named component — `CompoundInlineComponentDetector`
- **`deep-data-reach`** — A CLUSTER of elements in a sizeable template all reaching deep into the same nested object (≥2 distinct fields) — extract the shared mid-object into a component that takes it as a prop — `DeepDataReachDetector`
- **`deep-nested`** — Template markup nested far too deep — extract a subtree as its own component — `DeepNestedDetector`
- **`duplicate-element`** — Identical markup (3+ elements) repeated 2+ times — within a template or across components — extract one component — `DuplicateElementDetector`
- **`prop-drilling`** — A prop forwarded through a chain of 2+ components, none of which read it — piped from parent to leaf through dead conduits — `PropDrillingDetector`
- **`prop-mutation`** — A prop is WRITTEN — `v-model` bound to it, or `@event="prop = …"` — but props are read-only (a build error or a silent no-op) — `PropMutationDetector`
