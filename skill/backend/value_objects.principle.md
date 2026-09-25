Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a cluster
of values is passed around, returned, or reached into by string keys, it wants a name and a type — the type
IS the documentation, the validation, and the contract, all enforced by the compiler instead of by every
reader's memory.

Reach for a type when you: pass or return an `array<string, mixed>` bag whose keys are an undocumented
contract; pass three or more values that always travel together (a data clump); reach into a structured array
by string key (`$entry['title']`) instead of a typed object; keep adding parameters to an already-crowded
signature instead of grouping them; or pass a bare primitive that is really a concept — a `string $email`, a
`string $currency` + `int $amount`, a `string $key` with format rules — that wants a value object owning its
own validation.

Introduce the type at the boundary that first receives the data, or the method that first assembles it — not
several steps later, after it has been passed around as a loose array. A value object
introduced late just relabels data everyone already mishandled. This is fix-at-the-source applied to shape.