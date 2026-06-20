# The registry return contract

A registry has ONE job: you `register`/`add`/`put` items into a keyed store, then
look them up. Keep it to a small, total contract:

| Method | Returns | Meaning |
|---|---|---|
| `register($key, $item)` / `add` / `put` | `void` / `static` | Put an item in the store. |
| `has($key)` | `bool` | Is there an item for this key? |
| `get($key)` | `T` (or **throws**) | The item for this key — a miss is a wiring bug, so it throws a named exception. |
| `all()` / `values()` | `array<T>` / collection | The whole store. |
| `unregister($key)` | `void` / `static` | (optional) Remove an item. |

A registry **never hands absence across its boundary**. There is no `find(): Option`
and no `get(): ?T`: you ask `has()`, then `get()`. The decision "what if it is
missing?" belongs either to the registry (it throws) or to the caller's own `has()`
check — not to an `Option`/`null` that every call site has to unwrap.

## The sin — leaking absence out of a registry

Once a class is a *marked* registry (it extends `{{ namespace }}\Registry`, is named
`*Registry`, implements a `Registry` interface, or carries `#[Registry]`),
`RegistryReturnContract` enforces this:

- **An `Option<T>` return is ALWAYS the sin** — `find()`, `first()`, a predicate
  scan, any name. A registry does not hand an `Option` out for callers to unwrap,
  and renaming to `find*` does NOT help.
- **A `?T` / `T|null` getter is the sin too**, UNLESS the method NAME announces a
  miss is normal (a finder — see exemptions). A plain `get(): ?T` is a leak: return
  `T` and throw.

```php
// BAD — leaks an Option for every caller to unwrap
public function find(string $key): Option { return Option::find($this->handlers, $key); }

// BAD — a plain getter that hands back null
public function get(string $key): ?Handler { return $this->handlers[$key] ?? null; }

// GOOD — ask, then get; a miss is a wiring bug, so it throws a named exception
public function has(string $key): bool { return isset($this->handlers[$key]); }

public function get(string $key): Handler
{
    return $this->handlers[$key]
        ?? throw RegistryEntryNotFoundException::forKey($key);
}
```

An `Option` memo used INSIDE the registry is fine — it just must not be the public
return type.

## Exemptions — a NULLABLE finder, never an Option

`RegistryReturnContract` leaves exactly one shape alone: a **nullable** (`?T`) getter
whose NAME announces that absence is a normal, handled outcome —

- finder names: `find*`, `search*`, `try*`, `lookup*`, `*OrNull`, `*OrDefault`;
- `<thing>For<other>` directional lookups: `keyForClass($fqcn)`, `routeForName($n)`
  — a derive-the-mapping query, nothing to throw.

Even then, prefer `has()` + `get()` — a finder is a convenience, not the contract.
And note the asymmetry: a finder NAME excuses a `?T` return, **never** an `Option<T>`
one. `find(): Option` on a registry is the sin regardless of name.

Enforced by **RegistryReturnContract**.
Read it: `commandments scripture --prophet=RegistryReturnContract`.
