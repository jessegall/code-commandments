The relationship runs **both ways**:

- **Shape → name.** A class hand-rolling one of these shapes (an array + `register` + lookup; an
  add-and-iterate collection; a first-match `if/elseif` chain) should be **named for the role and extend
  the scaffolded base**, not reinvent the plumbing.
- **Name → shape.** A class *named* `*Registry`/`*Set`/`*Resolver` **must behave like one.** The suffix is
  a promise about the contract; breaking it misleads every reader.

And one cross-cutting rule: **a role class does ONE job.** A `*Registry` that also resolves, queries, or
assembles is hosting a second engine — extract it.

### Registry — a keyed store

`register(key, item)`, `get(key)`, `has(key): bool`, `all()`. Extends the scaffolded `Registry` base; named
`*Registry`.

- **`get($key)` returns the item or THROWS** — never `?T` / `Option` for the primary getter. A miss is a
  broken-state invariant, not a value to branch on (a `find()`/`try*` finder may return `Option`). This is
  the [`absence`](../absence/SKILL.md) resolve-or-throw rule, on the store.
- **Stays a pure store** — no resolution/query/assembly logic living inside it.

### Set — membership + iteration, unkeyed

`add(item)`, `has(item): bool` (identity), `all()`. Extends the scaffolded `Set` base; named `*Set`.

- Total + iterate-only: `has()` returns `bool`, never an Option/nullable leak.
- **No keyed `get(string)`.** If you want to look an item up *by key*, you wanted a **Registry**, not a Set.

### Resolver — first-match dispatch over predicates

A chain that runs predicates and returns the first match's result. Built with `Resolver::firstResultWins(...)`
(or `::collect(...)`), predicates composed from the kernel (`is`, `anyOf`, `allOf`, negation), branches via
`->then($factory)`. Named `*Resolver`; **must actually do first-match dispatch** (else rename).

Compose classifier checks with `anyOf()`/`allOf()`, not a `||`/`&&` chain of `->matches()`.

### Classify by type, not a name list

When a role needs to decide "is this one of mine?", classify from a **marker interface or the AST/type**,
never a hardcoded `const` array of class-name strings. A name list silently rots as classes are renamed or
added; a marker interface is checked by the compiler.