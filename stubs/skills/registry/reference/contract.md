# The registry return contract

A registry has ONE job: you `register`/`add`/`put` items into a keyed store, then
look them up. The contract that makes lookups legible is fixed:

| Method | Returns | Meaning |
|---|---|---|
| `register($key, $item)` / `add` / `put` | `void` | Put an item in the store. |
| `has($key)` | `bool` | Is there an item for this key? |
| `find($key)` | `Option<T>` | Look up where absence is a normal, handled outcome. |
| `get($key)` | `T` (or **throws**) | Look up where absence is a programming error — the key is an invariant. |
| `all()` / `values()` | `array<T>` / collection | The whole store. |

The discriminator between `find()` and `get()` is **whose fault is a miss**:

- `find($key): Option<T>` — the caller is *probing*; a miss is expected and the
  caller folds it (`->transform(...)->getOr($default)`, `->getOrThrow()`).
- `get($key): T` — the key is supposed to exist; a miss is a bug, so it **throws**
  a named exception (`{{ namespace }}\RegistryEntryNotFoundException::for($key)`),
  not `null`. Returning `null` here just defers the crash to a worse place.

## The leak — a marked registry getter returning `?T` / `Option` where it should throw

Once a class is a *marked* registry (it extends `{{ namespace }}\Registry` or is
named `*Registry`), `RegistryReturnContract` enforces the contract. A getter that
**returns `T|null` / `?T`** when its job is "give me the item for this key" is a
leak — it pushes the absence decision onto every call site instead of owning it:

```php
// BAD — a marked registry that hands back null; every caller now re-checks
public function get(string $key): ?Handler
{
    return $this->handlers[$key] ?? null;
}

// GOOD — find() for the probe, get() for the invariant
public function find(string $key): Option            // absence is normal
{
    return Option::find($this->handlers, $key);
}

public function get(string $key): Handler             // absence is a bug
{
    return $this->find($key)->getOrThrow(
        fn () => RegistryEntryNotFoundException::for($key),
    );
}
```

## Exemptions — where a `?T` / `Option` getter is honest, not a leak

`RegistryReturnContract` deliberately leaves these alone, because absence really
is a genuine handled outcome (not an invariant):

- **Finder-named getters** — `find*`, `search*`, `try*`, `lookup*`, `*OrNull`,
  `*OrDefault`. The name announces "this may legitimately return nothing."
- **`<thing>For<other>` directional lookups** — `keyForClass($fqcn)`,
  `routeForName($n)`: a derive-the-mapping query, not a store fetch; nothing to
  throw.
- **A predicate scan** — `first(callable $p): Option` is value-or-nothing by
  nature (like `search*`), not a key lookup.

If your getter is one of these, returning `Option<T>` is correct. If it is a
plain `get($key)` whose key should exist, return `T` and throw on a miss.

Enforced by **RegistryReturnContract**.
Read it: `commandments scripture --prophet=RegistryReturnContract`.
