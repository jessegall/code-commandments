# C# flow — guard at the top, keep the body flat — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-coalesced-loop-subject`** — a `foreach` over `items ?? []` (or `Enumerable.Empty<T>()`, or a new empty list) — the check for a missing collection is hidden in the loop header — `CoalescedLoopSubjectDetector`
- **`csharp-collection-mutated-while-iterated`** — a `foreach` that adds to or removes from the very collection it walks — the enumerator reads a collection that moved under it, which throws InvalidOperationException — `CollectionMutatedWhileIteratedDetector`
- **`deep-csharp-nesting`** — An `if`, loop or `switch` opening a fourth level of choices inside one C# method — an arrow of conditions and loops — `DeepNestingDetector`
- **`csharp-inline-throw`** — a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work — `InlineThrowDetector`
- **`csharp-non-counting-for`** — a `for` loop whose step assigns the next item instead of moving a counter — a walk written as a count — `NonCountingForDetector`
- **`csharp-unnamed-condition`** — `if (order.IsPaid && customer.Trusted && slot.IsOpen)` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it — `UnnamedConditionDetector`
