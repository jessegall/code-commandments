# Failing loud: the three forms

Once you've decided an absence is an invariant violation (see
`absence-taxonomy.md`), there are exactly three ways to fail loud. Pick by what
the absence *is*.

| Form | Use when | Backed by |
|---|---|---|
| **Exhaustive `match`** | A closed enum where every case yields a value — drop the `default` so an added case is a compile-time match error. | `ThrowOnUnhandledCase` |
| **Throw a named exception** | The impossible arm needs to crash with a locatable, named error; or a method must reject "no value" at its source. | `ThrowOnUnhandledCase`, `PreferTotalOverNullable` |
| **Make the method total** | The type has a natural *empty identity* and the empty value is a valid domain value — return the empty `T`, not null/none. | `PreferTotalOverNullable` |

## Form 1 — exhaustive match (drop the default)

### Bad
```php
return match ($type) {
    NodeType::A => new RendererA(),
    NodeType::B => new RendererB(),
    default     => null,            // add NodeType::C, forget it → silent null
};
```

### Good
```php
return match ($type) {
    NodeType::A => new RendererA(),
    NodeType::B => new RendererB(),
    NodeType::C => new RendererC(),
};                                  // a new case is now a compile-time match error
```

## Form 2 — throw a named exception

### Bad — the impossible arm yields none
```php
default => Option::none(),
```

### Good — name the impossibility
```php
default => throw UnhandledNodeType::for($type),
```

### Bad — a method every caller un-hedges
```php
private function root(): ?TreeNode { … }
// caller A: $this->root() ?? throw new RuntimeException('no root');
// caller B: $this->root()->id;     // blind deref — assumes non-null
```

### Good — make the contract honest once, at the source
```php
private function root(): TreeNode
{
    return $this->root ?? throw EmptyTreeException::create();
}
```

The named exception owns its message (see the `commandments-named-exceptions`
skill) — don't pass a raw string at the throw site.

## Form 3 — make the method total (return the empty identity)

When `T` has a natural zero value, the honest total form is **not** a throw and
**not** an Option — it is "return the empty `T`". `Option<T>` here is the same
partiality wearing a nicer coat: every caller still un-hedges it.

Empty identities: `array`→`[]`, `string`→`''`, `int`/`float`→`0`/`0.0`,
`bool`→`false`, a `Fluent`/Collection subclass or a no-arg / `::empty()` class →
`new T` / `T::empty()`.

### Bad — null / Option for a type that already has an empty value
```php
private function decode(string $p): ?ValueBag { … return null; }          // ❌ nullable
/** @return Option<ValueBag> */
private function decode(string $p): Option { … return Option::none(); }    // ❌ same partiality
```

### Good — "no data" IS an empty bag
```php
public function decode(string $p): ValueBag
{
    return is_file($p) ? ValueBag::fromJson(...) : new ValueBag;
}
// caller: $this->decode($p)->get('x')   — no null check, no Option ceremony
```

## When to reach for each form

- **Exhaustive match** — closed enum, every case produces a value, you control
  the enum. Always prefer this over a throwing `default`: it moves the error to
  compile time.
- **Throw** — the absence is a genuine bug AND the empty value would be *wrong*
  (e.g. `''` for a required path, `0` for a required id). Crash with a name.
- **Return empty `T`** — the type has an empty identity AND that empty value is a
  *valid* domain value ("no data" = empty bag, "no rows" = empty collection).

## When to leave it (don't force a fail-loud)

- A `default` arm that returns a real **value** is a legitimate fallback — not
  absence. Leave it.
- A case arm that *itself* yields null/none means the absence is genuine
  (kind 1), not just the fallthrough — that is `OptionDiscipline`'s domain,
  keep the Option.
- A method where **any** caller handles the miss (`?? $realDefault`, `?->`, a
  `=== null` branch, an Option consumed via `->unwrapOr($default)`) — the
  nullability is earned. Even for an empty-identity type: if returning the empty
  value would be a *wrong* domain value, the existing caller-side default is
  correct, leave it.
