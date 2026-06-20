# Composing the chain — transforms, factories, strategies, decorators

Once you have a composed `Resolver`, this is how the pieces fit: how to
pre-process the matched input (`->transform()`), what `->then()` should receive
(`->then()` factories), how to gather all matches instead of the first
(strategies), and when to wrap the kernel in a `ResolverDecorator` (domain
methods, NOT kernel passthroughs). This is the second half of what
`ResolverPattern` polices on a composed resolver.

All kernel classes live under `{{ namespace }}\Resolvers` and its sub-namespaces
(`Predicates`, `Strategies`, `Transforms`, `Factories`).

## `->transform()` — pre-process the matched input

When a chain entry needs to massage the input before the factory sees it, use
`->transform()` — the factory stays a first-class callable instead of a closure
that does `substr(...)` by hand.

### Bad — stripping the prefix inside the `->then()` closure

```php
HasPrefix::of('list:')->then(fn (string $t) => WireType::listOf(substr($t, strlen('list:'))));
```

### Good — a transform runs first, the factory stays a callable

```php
use {{ namespace }}\Resolvers\Predicates\HasPrefix;
use {{ namespace }}\Resolvers\Transforms\StripPrefix;

HasPrefix::of('list:')
    ->transform(StripPrefix::of('list:'))
    ->then(WireType::listOf(...));
```

`$transform` is any callable; reusable ones extend `Transform`
(`StripPrefix::of('list:')`). A transform PRE-PROCESSES the matched input; it is
not the same as the `->then()` factory, which PRODUCES the result.

## `->then()` factories — forward, callable, or named class

The `->then()` argument is the RESULT FACTORY. Three shapes, in order of
preference:

| The factory... | Use |
|---|---|
| forwards to one call (`fn ($r) => $this->expand($r)`) | the first-class callable `$this->expand(...)` |
| is a bare scalar/enum/constant needing no computation | the value itself: `->then(self::ORDER_DONE)` |
| does real domain work (a `new`, a multi-arg build) repeated across entries | a NAMED invokable factory class under `{{ namespace }}\Resolvers\Factories` |

### Bad — 3+ inline domain factory closures

```php
Resolver::firstResultWins(
    DescriptorKeyIs::of(InputBagNode::KEY)->then(fn ($r) => $this->expandInputBag($r->descriptor, $r->node)),
    HasItemShape::make()->then(fn ($r) => $this->expandForEach($r->descriptor, $r->node)),
    DescriptorKeyIs::of(AgentNode::KEY)->then(fn ($r) => $this->expandAgent($r->descriptor, $r->node)),
);
```

### Good — name each factory

```php
use {{ namespace }}\Resolvers\Factories\ExpandInputBag;

final class ExpandInputBag
{
    public function __invoke(DescriptorExpansionRequest $r): Node
    {
        return /* ... */;
    }
}

Resolver::firstResultWins(
    DescriptorKeyIs::of(InputBagNode::KEY)->then(new ExpandInputBag(...)),
    // ...
);
```

The kernel ships two ready-made factories there: `Capture::make()` (identity —
return the value unchanged) and `Wrap::make()` (`$v => [$v]`).

## Strategies — first match vs. all matches

| Strategy | Use |
|---|---|
| `Resolver::firstResultWins(...)` | first non-null wins → `?T` |
| `Resolver::collect(...)` | gather ALL matches → `list<T>` |
| `Resolver::using($strategy, ...entries)` | any other combine rule (a custom `ResolveStrategy`) |

```php
Resolver::collect(
    IsCreatableField::for($output)->then(...),
    IsControlTarget::make()->then(...),
)->resolve($descriptor);   // list — every match
```

## Layout — let a big chain breathe

A big chain reads like a dispatch TABLE. Once an entry wraps (a `->and()` and/or
a `->then()` on its own line) or the list is long, give EACH entry its own block
with a blank line between, so predicate→factory pairs line up and scan
top-to-bottom. A short chain (two or three single-line entries) stays compact —
the spacing earns its keep only once entries wrap.

## `ResolverDecorator` — domain methods, not kernel passthroughs

When a chain repeatedly states the same shape — e.g.
`HasPrefix::of(P)->transform(StripPrefix::of(P))` declares the prefix P TWICE per
entry and the two can silently drift — extend the `ResolverDecorator` base and
add ONE domain method that declares P once.

### Bad — P declared twice per entry, repeated across the chain

```php
Resolver::firstResultWins(
    HasPrefix::of('list:')->transform(StripPrefix::of('list:'))->then(WireType::listOf(...)),
    HasPrefix::of('resource:')->transform(StripPrefix::of('resource:'))->then(WireType::resource(...)),
);
```

### Good — a domain method declares the prefix once

```php
use {{ namespace }}\Resolvers\ResolverDecorator;

final class WireTypeTokenResolver extends ResolverDecorator
{
    public static function make(): self
    {
        return new self();
    }

    public function stripPrefix(string $prefix, callable $factory): static
    {
        return $this->add(
            HasPrefix::of($prefix)->transform(StripPrefix::of($prefix))->then($factory),
        );
    }
}

WireTypeTokenResolver::make()
    ->stripPrefix('list:', WireType::listOf(...))
    ->stripPrefix('resource:', WireType::resource(...))
    ->resolveInstanceOf($token, WireType::class);
```

### When to reach for the decorator

- A composed chain repeats the same multi-call entry shape (the doubled
  prefix above) — extract it to ONE domain method via `$this->add(...)`.
- You want a resolver that reads as your DOMAIN's operations, not the kernel's.

### When to leave it (use the kernel directly)

- Add DOMAIN methods only. Do NOT re-expose the kernel with generic passthroughs
  like `->equals()` / `->when()` / `->then()` — a decorator that just forwards
  the kernel is pure indirection. For those, use the `Resolver` kernel directly.
- The base intentionally has no `make()` — a domain resolver controls its own
  construction (a parameterless `make()`, or `new` with dependencies).

Enforced by `ResolverPattern` (the `inline-then-factories`, `prefix-substr`, and
`doubled-strip-prefix` findings). Read the full rule:
`commandments:scripture --prophet=ResolverPattern`.
