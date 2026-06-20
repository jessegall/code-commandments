# The Resolver pattern — dispatch chains, composite predicates, honest names

A `Resolver` takes ONE input and dispatches it to one of several outputs by a
chain of conditions — first match wins. Each condition is a NAMED `Predicate`
object paired with a result factory via `->then(...)`. This is the positive form
of what `ResolverPattern` and `ResolverNamingHonesty` enforce.

The kernel lives under `{{ namespace }}\Resolvers` (the `Resolver` itself and
`ResolverDecorator`), with predicates under `{{ namespace }}\Resolvers\Predicates`
and strategies under `{{ namespace }}\Resolvers\Strategies`.

## Mode 1 — dispatch chain → composed Resolver

A method that is a first-match dispatch chain (3+ predicate guards, or
`match (true)` arms, all producing one type) is a resolver in disguise.

### Bad — a hand-rolled guard chain

```php
public static function parse(?string $token): self
{
    if ($token === null)                       { return self::mixed(); }
    if (str_starts_with($token, 'resource:'))  { return self::resource($token); }
    if (str_starts_with($token, 'list:'))      { return self::listOf($token); }
    if (in_array($token, self::SCALARS, true)) { return self::scalar($token); }

    return self::classRef($token);
}
```

### Good — compose a Resolver, each guard a NAMED Predicate `->then(factory)`

```php
use {{ namespace }}\Resolvers\Resolver;
use {{ namespace }}\Resolvers\Predicates\IsNull;
use {{ namespace }}\Resolvers\Predicates\HasPrefix;
use {{ namespace }}\Resolvers\Transforms\StripPrefix;

public static function parse(?string $token): self
{
    return Resolver::firstResultWins(
        IsNull::make()->then(WireType::mixed(...)),
        HasPrefix::of('resource:')->transform(StripPrefix::of('resource:'))->then(WireType::resource(...)),
        HasPrefix::of('list:')->transform(StripPrefix::of('list:'))->then(WireType::listOf(...)),
        IsScalarToken::make()->then(WireType::scalar(...)),   // domain predicate
    )->resolve($token) ?? self::classRef($token);
}
```

`->resolve()` returns `?WireType` (first non-null wins). To guard the result to
a type, use `->resolveInstanceOf($token, WireType::class)` instead of a
hand-rolled `instanceof` after `resolve()`.

## Mode 2 — boolean chain → composite Predicate

A method returning `bool` stitched from 3+ guards is a composite Predicate, not
a resolver. Build it from named Predicate objects and the kernel combinators.

### Bad

```php
public function isDispatchable(WireType $type): bool
{
    if ($type->isMixed())  { return true; }
    if ($type->isList())   { return true; }
    if ($type->isScalar()) { return true; }

    return false;
}
```

### Good

```php
use {{ namespace }}\Resolvers\Predicates\AnyOf;

AnyOf::of(IsMixed::make(), IsListType::make(), IsScalarType::make());
```

Combine with `->and()` / `->or()` / `->not()` (the kernel `AllOf` / `AnyOf` /
`Negated`), reusing `IsNull` / `IsEnum` where they fit.

## Mode 3 — extract a composed Resolver's inline predicates

A composed Resolver's entries must be NAMED Predicates. An entry that inlines the
test (`fn ($x) => $x === null ? ... : null`) is half-done extraction.

**3+ inline predicate closures in one `Resolver::...(...)` is a SIN** — it is the
original chain with extra boilerplate. Give each inline test a class:

| Inline test | Reuse / extract to |
|---|---|
| `$x === null` | `IsNull::make()` (kernel) |
| `$x instanceof SomeEnum` | `IsEnum::for(SomeEnum::class)` (kernel) |
| `$x instanceof SomeClass` (dispatch on object type) | `HasClass::of(SomeClass::class)` (kernel) |
| `str_starts_with($x, 'list:')` | `HasPrefix::of('list:')` (kernel) |
| generic, not in the kernel | a new class in the SHARED `{{ namespace }}\Resolvers\Predicates` |
| domain-bound (reads a type's constants — `self::SCALARS`, `WireType::MIXED`) | the resolver's OWN `{{ namespace }}\Resolvers\<Name>\Predicates` |
| an entry that just FORWARDS to one method (`fn ($r) => $this->candidate($r)`) | the first-class callable `$this->candidate(...)` |

### Predicate conventions

- NAMED STATIC FACTORY, never `new` at the call site: `HasPrefix::of('list:')`,
  `IsEnum::for(NodeType::class)`, `IsNull::make()`. PRIVATE constructor so the
  factory is the only way in.
- A predicate is for the CHAIN. NEVER instantiate one to call it once inline:
  `(new IsNull())($x)` — or `$p = new IsNull(); $p($x)` — is WORSE than the plain
  `$x === null`. Use the plain test for a one-off, or put it in a chain.

## Honest naming — `*Resolver` means dispatch

The `Resolver` suffix promises first-match dispatch via the kernel. A class that
carries the name but does NO dispatch (a lookup, a reflection read, a string
interpolator) is misnamed.

| Misnamed `*Resolver` that actually... | Rename to |
|---|---|
| maps a key to a registered value (`$this->map[$key] ?? throw ...`) | `*Registry` / `*Map` |
| reads a property/attribute (reflection) | `*Reader` / `*Accessor` |
| builds a thing (if/instanceof construction) | `*Factory` / `*Locator` |
| interpolates a string | `*Interpolator` |
| recursively flattens / expands | `*Flattener` / `*Expander` |

## When to reach for it

- A method maps ONE input to one of several outputs by a chain of conditions
  (3+ predicate guards, or `match (true)` arms) producing one type.
- A `bool` method is stitched from 3+ guard conditions → composite Predicate.
- A composed Resolver still carries inline predicate tests → name them.
- A class genuinely does first-match dispatch → name it `*Resolver` and use the
  kernel.

## When to leave it

- The branches are not pure dispatch — they transform, throw, or return
  unrelated shapes (a `try`/`catch` procedure, a validity gate where every guard
  early-returns the SAME fallback).
- **Multi-input logic**: the guards reference two or more of the method's
  parameters across their conditions AND results — `compatible($source, $target)`
  tests a relationship between a pair; `checkValue($field, $value)` guards on
  `$field` then validates `$value`. A Resolver dispatches ONE input.
- A `match` on an **enum subject** — that is the enums skill's concern
  (behaviour on the case), not a resolver.
- **Reflection introspection helpers** (`propertyAllowsNull(ReflectionType $t)`)
  — infrastructure glue, not a reusable domain Predicate.
- A lone inline test that reads clearly where it is and is a genuine one-off.
- A `*Resolver` whose name is the domain's ubiquitous language, even if the
  rename would otherwise apply (naming is advisory — weigh the cross-repo ripple).

Enforced by `ResolverPattern` and `ResolverNamingHonesty`. Read the full rules:
`commandments:scripture --prophet=ResolverPattern`.
