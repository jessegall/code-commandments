The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model to
hang behaviour on. Reach for those. Raw `->input()`, an untyped `->get()`, `app()`-in-a-method, a `where()`
chain repeated at call sites, and a column-poke-then-`save()` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.

Read request input through the request's **typed accessors**, exposed as **named getter methods on the
request class** — the one place the type is settled, so every call site reads a typed value by intent
instead of re-coercing `mixed`. An MCP tool's input is a request like any other: give each tool its own
named request class (the analogue of a `FormRequest`), with its keys, rules and types in one place, and
read *that* — never the raw request inside `handle()`.

Hold every dependency as a required constructor parameter, never resolved by hand from the container.
Express a query concept that recurs across call sites as a **named Eloquent scope**, so the column knowledge
lives in one place instead of being re-typed wherever you query. And mutate a model through
**intention-revealing methods** (`$order->markPaid()`) that say what changed and why — not a bare
`update([...])` or a set-property-then-`save()` smeared across the call site.