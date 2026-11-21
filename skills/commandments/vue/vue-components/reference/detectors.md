# Vue components — extract repetition, deep reaches and dispatched views — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`compound-inline-component`** — A compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`…) assembled inline with a substantial body — extract it into its own named component. — `compound-inline-component`
- **`deep-data-reach`** — A group of elements in a sizeable template that all reach deep into the same nested object (≥2 distinct fields) — extract the shared mid-object into a component that takes it as a prop. — `deep-data-reach`
- **`deep-nested`** — Template markup nested far too deep — extract a subtree as its own component — `deep-nested`
- **`duplicate-element`** — Identical markup (3+ elements) repeated 2+ times — within a template or across components — extract one component — `duplicate-element`
- **`inline-case-views`** — A dispatch whose cases each render a whole view inline — one component doing a job per case — `inline-case-views`
- **`near-duplicate-element`** — Markup with one skeleton repeated 2+ times — the same tags, attributes and nesting binding different data — within a template, across components, or as two components' whole templates — `near-duplicate-element`
- **`oversized-component`** — A component whose template renders more elements than the project's declared budget — one component doing several jobs — `oversized-component`
- **`prop-drilling`** — A prop forwarded through a chain of 2+ components, none of which read it — passed down through components that only pass it further. — `prop-drilling`
- **`prop-mutation`** — A prop is written to — `v-model` bound to it, or `@event="prop = …"` — but props are read-only (a build error or a silent no-op). — `prop-mutation`
