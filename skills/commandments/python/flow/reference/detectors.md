# Python flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-coalesced-loop-subject`** — `for x in d.get(k, [])` / `for x in y or []` over a parameter — whether the caller handed anything over, decided in the loop header instead of stated as a guard — `CoalescedLoopSubjectDetector`
- **`python-collection-mutated-while-iterated`** — a loop that appends to, removes from or deletes out of the very collection it walks — the iterator reads a sequence that moved under it, so an element is skipped or it raises — `CollectionMutatedWhileIteratedDetector`
- **`deep-python-nesting`** — An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops — `DeepNestingDetector`
- **`python-unnamed-condition`** — `if settings.auto and not self.said and event.at > self.since:` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it — `UnnamedConditionDetector`
