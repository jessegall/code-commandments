The sibling skill [`spatie-data`](../spatie-data/SKILL.md) teaches how to *author* a `Data` class. This one
teaches how to *feed* it. They share one root: the `Data` class is a declarative machine — `::from()`,
`::collect()`, casts, `#[DataCollectionOf]`, `#[Computed]`, and name mappers already do the array↔object
work. A **call site** that re-does any of it by hand is redundant, and it duplicates a mapping that should
live in exactly one place — the class.

### The one rule: pass the simplest input the class can build from

`::from([...])` runs the whole pipeline **recursively**. Every value in that array is fed to the matching
property through its own hydration — nested `Data`, typed collection, enum, or date. So the value you write
should be the **raw material**, not the finished object.

### Nested `Data` and collections auto-hydrate — don't wrap them

A property typed as a nested `Data` builds itself from a plain array; a `#[DataCollectionOf(E)]` builds each
element from an array. So `X::from([...])` sitting in a parent `::from` array is pure ceremony:

- `'sandbox' => ConsoleSandboxCopy::from(['label' => 'x'])` → just `'sandbox' => ['label' => 'x']`.
- `'modes' => [Mode::from([...]), Mode::from([...])]` → just `'modes' => [[...], [...]]`.

Pass the array; the parent nests it. (The one-argument, array-literal form is the redundant one — an
**object** source like `X::from($model)` is a real conversion, not this sin.)

### Enums and dates auto-cast — pass the scalar

Spatie casts enums (native) and `DateTimeInterface` (built-in) straight from their raw value. So constructing
them at a hydration site is redundant:

- `'status' => WorkflowRunStatus::from($raw)` → just `'status' => $raw`.

(A `tryFrom`, or a `new DateTime($x, $tz)` / `createFromFormat(...)` that carries a timezone or format the
default cast wouldn't reproduce, is **not** redundant — those change the semantics.)

### A derivation belongs in a cast, not a call-site `array_map`

When each element is **derived** from a simpler value through a factory — `array_map(E::for($enum), $cases)` —
auto-hydration can't help (the input isn't the element's array shape). But a **cast** can: a `#[WithCast]`
(or per-item `IterableItemCast`) on the collection property owns the `enum → E` derivation once, and every
caller just passes the raw list. A factory that closes over services/`$this` can't move into a per-item cast,
so it stays at the call site — that's the boundary of this rule.

### Don't build a `Data` only to discard it

Building `X::from([...])->toArray()` constructs a typed object just to flatten it back to an array — either
pass the source array, or type the receiving slot as `X` and pass the object. And to serialize a `Data`
object, call `->toArray()` — never hand-write `['a' => $d->a, 'b' => $d->b, …]`, which silently drifts from
the class the moment a field is added.

### Derive and map on the class, not the caller

A field that is a pure function of other fields is a `#[Computed]` property — computed once in the class, not
recomputed at every construction site. A boundary that renames keys (snake ↔ camel) is one class-level
`#[MapInputName(SnakeCaseMapper::class)]` — not a hand-written translation array at each `::from`.