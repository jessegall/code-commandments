# Named branch factories — name the non-trivial `->then()`

A resolver chain reads like a dispatch TABLE — each line a predicate paired with
a result factory via `->then()`. When the factory is a trivial constant, an
inline closure is fine. When it captures `$this` and builds something, it has a
name hiding in it: pull it onto a dedicated `*Factory` class as a static method
returning `callable`, so the call site stays declarative, the factory is named
and reusable, and its dependency is built in one place.

## The core move

### Bad — an inline factory that captures a dependency and does work

```php
Resolver::firstResultWins(
    IsObjectType::for($this->objects)
        ->then(fn (FieldTypeRequest $r) => CreatableFieldType::object(
            $this->objects->slugForToken((string) $r->type)->unwrap(),
        )),
);
```

The closure captures `$this->objects` and builds a `CreatableFieldType` — that
is a named factory hiding inside a lambda.

### Good — a named factory; the call site is a table

```php
final class FieldFactory
{
    /** @return callable(FieldTypeRequest): CreatableFieldType */
    public static function object(SchemaTypeRegistry $objects): callable
    {
        return static fn (FieldTypeRequest $r) => CreatableFieldType::object(
            $objects->slugForToken((string) $r->type)->unwrap(),
        );
    }
}

// …->then(FieldFactory::object($this->objects))
```

The resolver reads as a table, the factory has a name and a documented return
type, and its dependency is built in one place and reusable across resolvers.

## What fires

A `->then(<closure>)` whose closure body BOTH:

1. references `$this` (a constructor dependency to home on the factory class), AND
2. contains real work — a method call, a static call, or a `new`.

## What does not fire

- A trivial constant return: `fn () => SchemaFieldType::Int`,
  `fn () => self::CONST`, `fn () => $this->prop` (a bare property) — extracting a
  one-liner constant is pure ceremony.
- A first-class callable to an already-named method: `->then(Capture::make())`,
  `->then(T_Array::empty(...))`, `->then(WireType::scalar(...))` — already named.
- A closure that does **not** capture `$this` (no dependency to home, no shared
  build) — a genuine one-off.

## Decision table

| `->then(...)` argument | Verdict | Do |
|---|---|---|
| `fn ($r) => $this->dep->build($r)->unwrap()` (captures `$this`, does work) | warn | Extract to `SomeFactory::name($this->dep)` returning `callable`. |
| `fn () => SomeEnum::Case` / `fn () => self::CONST` | leave | Trivial constant — inline. |
| `fn () => $this->prop` (bare property) | leave | Trivial — inline. |
| `->then(Capture::make())` / `->then(T::scalar(...))` | leave | Already a named callable. |
| `fn ($r) => Thing::from($r)` (does work, no `$this`) | leave | No dependency to home; one-off. |

## When to reach for it

- The closure captures a constructor dependency AND builds something.
- The same factory logic would be reused across resolvers.
- You are escaping a private builder onto a shared, named, reusable home.

Extract to a `*Factory` static method returning `callable`, e.g.
`FieldFactory::object($this->dep)`.

## When to leave it

- A trivial one-off constant/enum/property return — extracting it is ceremony.
- A first-class callable reference to an already-named method.
- A closure with no captured `$this` and no reuse — keep it inline.

This is **advisory, never a sin** — extracting a class is a refactor, so weigh
reuse. When unsure, ask: does this factory deserve a name? A captured dependency,
reuse across resolvers, or escaping a private builder = yes; a trivial one-off
constant = no.

Enforced by **PreferNamedBranchFactory**. Scripture:
`commandments:scripture --prophet=PreferNamedBranchFactory`.
