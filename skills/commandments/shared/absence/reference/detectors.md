# Absence — ask whether it is there, directly and once — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`blank-string-default`** — A blank string default stands for a missing required value — `BlankStringDefaultDetector`
- **`cancelled-coalesce`** — A fallback compared against the same value it falls back to (`(name ?? '') !== ''`, `(name or "") != ""`): missing and empty end up in one branch without saying so — `CancelledCoalesceDetector`
- **`capability-probe-hub`** — Several methods each ask the same field if it has a capability before using it — `CapabilityProbeHubDetector`
- **`coalesced-loop-subject`** — The loop header replaces a missing collection with an empty one — `CoalescedLoopSubjectDetector`
- **`conditional-array-spread`** — A spread branch supplies an empty collection as a placeholder — `ConditionalArraySpreadDetector`
- **`de-nulled-finder`** — Every caller takes a nullable result as present — `DeNulledFinderDetector`
- **`manufactured-fake-fill`** — An empty scalar fills a slot that expects a meaningful value — `ManufacturedFakeFillDetector`
- **`masked-invariant`** — Masking an optional field behind a literal hides a value the design requires — `MaskedInvariantDetector`
- **`nullable-callback`** — A callback is declared optional, checked for absence, and then invoked — `NullableCallbackDetector`
- **`nullable-registry-lookup`** — A keyed store returns absence on a miss — `NullableRegistryLookupDetector`
- **`phantom-nullable`** — Every dereference assumes the declared optional value is present — `PhantomNullableDetector`
- **`placeholder-filled-data`** — A blank string fills a required string field in a data type — `PlaceholderFilledDataDetector`
- **`raw-decoded-array-return`** — A function returns decoded data before checking and giving it a type — `RawDecodedArrayReturnDetector`
