# TypeScript flow — turn away at the top, then go straight — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`typescript-collection-mutated-while-iterated`** — a `for…of` or `forEach` that splices, shifts, sorts or pops the very array it walks — every element after the change has moved, so the walk skips one or reads one twice — `CollectionMutatedWhileIteratedDetector`
