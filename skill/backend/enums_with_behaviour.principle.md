Two moves, always together:

1. **Seal the set.** A fixed range of values — statuses, kinds, modes — is a **native backed enum**. Not
   raw string literals scattered across comparisons, not a `const` class of scalars, not a `string` field
   that "happens to" hold one of five values.
2. **Put the behaviour on the case.** The knowledge keyed off the set — a per-case value, a per-case
   decision — lives as a **method on the enum**, computed once with an exhaustive `match`. A `match` /
   `switch` over an enum *at a call site* is that method, homeless.

Sealing the set without moving the behaviour just relocates the `match` statements; the win is the enum
*answering for itself*.

### When to use this skill

Reach for this the moment you write:

- a **fixed set of string/int values** used as discrete choices (compared, `in_array`'d, switched on);
- a **`match` / `switch` over an enum** — especially the *same* enum in more than one place;
- a `match` / `switch` over **strings that mirror an enum's cases** (`'pending'`, `'done'` …);
- a **`const` class** of scalar values used as a closed set;
- a **`string`/`int` property** whose value space is actually closed.