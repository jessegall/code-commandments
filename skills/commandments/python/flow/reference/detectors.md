# Python flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-collection-mutated-while-iterated`** — a loop that appends to, removes from or deletes out of the very collection it walks — the iterator reads a sequence that moved under it, so an element is skipped or it raises — `CollectionMutatedWhileIteratedDetector`
