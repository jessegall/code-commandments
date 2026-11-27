# Absence — decide it where the value is born — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`blank-string-default`** — A blank string default stands for a missing required value — `blank-string-default`
- **`blank-string-on-the-wire`** — A string sent over the wire that the frontend reads as absent when it is blank. — `blank-string-on-the-wire`
- **`cancelled-coalesce`** — A fallback compared against the same value it falls back to (`(name ?? '') !== ''`, `(name or "") != ""`): missing and empty end up in one branch without saying so — `cancelled-coalesce`
- **`capability-probe-hub`** — Several methods each ask the same field if it has a capability before using it — `capability-probe-hub`
- **`checked-then-read`** — One lookup is asked twice: a key is tested (`has(k)`, `isset($a[k])`) and then read with the same key, instead of one read that answers with the absence handled — `checked-then-read`
- **`coalesced-loop-subject`** — The loop header replaces a missing collection with an empty one — `coalesced-loop-subject`
- **`conditional-array-spread`** — A spread branch supplies an empty collection as a placeholder — `conditional-array-spread`
- **`csharp-null-forgiven`** — The null-forgiving `!` on a value declared nullable — the compiler told the caller it may be null, and `!` silences it instead of deciding — `csharp-null-forgiven`
- **`de-nulled-finder`** — Every caller takes a nullable result as present — `de-nulled-finder`
- **`defended-certain-field`** — An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, so the code doubts something the design already rules out. — `defended-certain-field`
- **`erased-null-object`** — A blank-rendering Null Object written into a `string` slot — coerced back to `''` — `erased-null-object`
- **`false-for-nothing`** — A return typed `X|false`: `false` stands in for "nothing" or "failed", and every caller must test for it. — `false-for-nothing`
- **`falsely-optional-field`** — A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen — `falsely-optional-field`
- **`manufactured-fake-fill`** — An empty scalar stands in for a missing key a lookup is asked for — `manufactured-fake-fill`
- **`masked-invariant`** — Masking an optional field behind a literal hides a value the design requires — `masked-invariant`
- **`match-default-returns-null`** — The default arm silently returns an absent value — `match-default-returns-null`
- **`nullable-callback`** — A callback is declared optional, checked for absence, and then invoked — `nullable-callback`
- **`nullable-registry-lookup`** — A keyed store returns absence on a miss — `nullable-registry-lookup`
- **`option-as-nullable`** — `Option<T>` used as if it were nullable — `?Option`, `Option | null`, `unwrapOr(null)` — `option-as-nullable`
- **`phantom-nullable`** — Every dereference assumes the declared optional value is present — `phantom-nullable`
- **`placeholder-filled-data`** — A blank string fills a required string field in a data type — `placeholder-filled-data`
- **`raw-decoded-array-return`** — A function returns decoded data before checking and giving it a type — `raw-decoded-array-return`
