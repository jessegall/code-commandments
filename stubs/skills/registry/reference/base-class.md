# Extend the scaffolded `Registry` base — and don't defeat its store

`commandments:scaffold` generates `{{ namespace }}\Registry`, an abstract base
that already implements the whole contract (`register`/`registerMany`/`has(): bool`/
`get(): T` throwing `RegistryEntryNotFoundException`/`all()`/`values()`) over one
protected store — and deliberately ships NO `find(): Option`. **Extend it instead of
hand-rolling register/has/get** — then the contract lives, and is enforced, in one
place:

```php
// GOOD — the contract is inherited; you only declare the value type
final class HandlerRegistry extends Registry
{
    protected function type(): string
    {
        return Handler::class;
    }
}
```

Hand-rolling the same three methods on a bare class re-derives the contract (and
usually leaks — see `reference/contract.md`). If two or more classes hand-roll it,
`RegistryPattern` will tell you to extract this base.

## The bypass trap — overriding `all()` to read your own store

The base's mechanism is one store: `register()` writes it, `has()`/`get()`/
`all()` read it. Override `all()` to read a **different** (private) store without
calling `parent::all()`, and you sever that mechanism — the inherited
`register()`/`registerMany()` still write the base store, but nothing reads it, so
**calling them is a silent no-op**:

```php
// BAD — inherited register() is now DEAD; nothing reads $items
final class ResourceRegistry extends Registry
{
    private array $resources = [];

    public function all(): array
    {
        return $this->resources ??= $this->discover();   // base $items never read
    }
    // register()/registerMany() inherited → write $items → ignored
}
```

You have two honest options:

```php
// GOOD (a) — it IS registered into: use the base store
public function all(): array
{
    return [...parent::all(), ...$this->extra];          // still the base mechanism
}
```

```php
// GOOD (b) — it is NOT registered into, it is DISCOVERED: it is a catalog,
// not a registry. Stop extending Registry; name it *Catalog/*Map (see
// reference/naming.md) and own your store outright.
final class ResourceCatalog
{
    private array $resources;
    public function all(): array { return $this->resources ??= $this->discover(); }
}
```

The test: **is anyone meant to `register()` into this class?** If yes, use the
base store. If no — it is built/discovered, not registered — it is a catalog
wearing the base.

Enforced by **RegistryBaseBypass**.
Read it: `commandments scripture --prophet=RegistryBaseBypass`.
