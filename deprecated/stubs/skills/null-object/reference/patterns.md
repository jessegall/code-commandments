# Null Object & empty-instance patterns

The goal of every pattern below: the caller stops asking "is it null?". Either
the value has a no-op behaviour, or its empty form already means "nothing here".

## Pattern 1 — empty collection over `T | null`

A collection-like type already has an empty identity, so an empty instance IS
"no value". Returning null forces every caller to null-guard before iterating.

Bad — null is the absence:

```php
public function rows(): ?Collection
{
    return $this->found ? collect($this->found) : null;
}

// every caller pays the guard:
if (($r = $repo->rows()) !== null) {
    foreach ($r as $row) { /* ... */ }
}
```

Good — empty IS the absence:

```php
public function rows(): Collection
{
    return $this->found ? collect($this->found) : new Collection();
}

// caller iterates straight through:
foreach ($repo->rows() as $row) { /* ... */ }
```

Picking the empty instance:

| T | Empty instance |
|---|---|
| `array` | `[]` |
| class with a static no-arg `empty()` / `make()` | `T::empty()` |
| no-arg-constructible class | `new T()` |

This is exactly what `PreferEmptyOverNull` flags. It fires on a return type,
**public/protected** typed property, or null-defaulted param whose type strips to
one collection-like T (array, `Collection`/`LazyCollection`/`DataCollection`/
`Fluent`, or a project class that is `Countable`/`Traversable`/`Arrayable`).

## Pattern 2 — Null Object default over a body-normalized nullable param

A param typed `T | null $x = null` whose body's first act is to replace null
with a real default. The signature says null is acceptable; the body refuses it.

Bad — the signature lies, the default hides in the body, `??=` branches at
every call:

```php
public function run(
    callable | null $shouldExit = null,
    Observer | null $observer = null,
): void {
    $shouldExit ??= static fn () => false;
    $observer ??= new NullObserver();
    // ...
}
```

Good — the default lives on the signature, no normalization:

```php
public function run(
    callable $shouldExit = new {{ namespace }}\NullCallable,
    Observer $observer = new NullObserver(),
): void {
    // no `??=` — the default is right there in the signature.
}
```

Notes that match `PreferNullObjectDefaults`:

- A **constant** RHS (literal, `Class::CONST`, `Enum::CASE`, `new C(constArgs)`)
  is auto-fixable — the RHS moves to the param default and `T | null` drops to
  `T`. Run `commandments:repent` after a `[AUTO-FIXABLE]` finding.
- A **closure literal** can't be a PHP parameter default. Use the scaffolded
  `{{ namespace }}\NullCallable` (a do-nothing `__invoke`) and register the type
  in the prophet's `null_objects` config map so the auto-fix knows the
  replacement: `'callable' => {{ namespace }}\NullCallable::class`.
- A **runtime call** RHS (`$this->resolveX()`, `app(Foo::class)`) is left alone —
  there is a real reason it is computed at call time.

## Pattern 3 — kill the `?->` chain with a Null Object

A nullable receiver accessed via `?->` two or more times with no meaningful null
branch morally means "I never act differently on null". A Null Object default
removes every `?`.

Bad:

```php
private Observer | null $observer = null;

public function execute(Command $cmd): void
{
    $this->observer?->executing($cmd);
    $this->doWork($cmd);
    $this->observer?->completed($cmd);
}
```

Good — a Null Object whose methods are no-ops:

```php
private Observer $observer = new NullObserver();

public function execute(Command $cmd): void
{
    $this->observer->executing($cmd);   // no-op when null-object
    $this->doWork($cmd);
    $this->observer->completed($cmd);
}
```

A Null Object implements the same interface; every method is a safe no-op (or
returns its own empty/identity value). `{{ namespace }}\NullCallable` is the
shipped one for the `callable` slot.

This `?->`-chain shape is `PreferNullObjectDefaults` Pattern B — a **warning**,
because the receiver could legitimately be an optional value object.

## When to reach for it

- The absent case has a **sensible no-op** (observer that observes nothing,
  logger that logs nothing, callback that returns false) → Null Object.
- T is **collection-like** and callers iterate / `->all()` / `count()` it → empty
  instance, drop the `| null`.
- A nullable param is **always de-nulled** at the top of the body → hoist the
  default to the signature.

## When to leave it

- A caller must **distinguish ABSENT from EMPTY** — cache miss vs empty result,
  404 vs found-but-empty. Then null (or an `Option`) carries information an empty
  value cannot. See `reference/vs-option.md`.
- The receiver is a **value-object-style nullable** (`DateTimeImmutable | null`,
  `BackedEnum | null`) accessed via `?->` — there null is a genuine optional
  value, not a stand-in for "no behaviour". `PreferNullObjectDefaults` Pattern B
  deliberately does not fire here.
- A **private** nullable collection property used as a lazy-init memo
  (`private array|null $resources = null;` resolved with `??=`), where null =
  "not loaded yet" and `[]` = "loaded but empty" genuinely differ.
