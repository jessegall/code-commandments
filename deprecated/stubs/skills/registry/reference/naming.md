# Naming honesty — earn `*Registry`, or pick the honest other name

A class shaped like a registry (you `register`/`add`/`put` into a keyed store,
then `find`/`has`/`get` out of it) should be **named** so the shape is legible —
and the name is the opt-in to enforcement. `RegistryNamingHonesty` flags a
register-and-look-up class whose name hides the contract.

## The trio that makes it a registry

All three present → it is a registry, name it `*Registry`:

1. you **put items in** by a key (`register`/`add`/`put`/`set`/`bind`…), into
2. a **keyed store** it owns (`$this->items[$key] = …`), and
3. you **look them up** by key (`find`/`has`/`get`/`all`).

```php
// BAD — it is a registry, but the name hides it (and dodges enforcement)
final class HandlerThing { /* register() + $handlers[] + get() */ }

// GOOD — the name announces the contract; RegistryReturnContract now guards it
final class HandlerRegistry extends Registry { /* … */ }
```

## Pick the honest name — `*Registry` is not the only shape

Reach for a different name when the shape is genuinely different, and you opt OUT
of registry enforcement honestly:

| Name | Use when |
|---|---|
| `*Registry` | You **register** items in, then look them up by a typed contract. |
| `*Map` / `*Catalog` | A store that is **discovered/built**, not registered into (no public `register`). A read-only lookup table. |
| `*Resolver` / `*Factory` | You **compute on demand** — there is no owned store; each call derives/builds the result. |
| `*Collection` / `*Bag` | A typed container you iterate, with no key-lookup contract. |

The marker — extending `{{ namespace }}\Registry` or the `*Registry` name — is
the **opt-in to strict enforcement**: only a marked registry's getters must
return `T`-or-throw. So name it `*Registry` when you want the contract enforced;
name it `*Map`/`*Catalog`/`*Resolver` when the contract genuinely does not apply.
Don't hide a real registry behind a vague name to escape the rule — fix the shape
or fix the name.

Enforced by **RegistryNamingHonesty**.
Read it: `commandments scripture --prophet=RegistryNamingHonesty`.
