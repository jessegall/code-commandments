# Extend the scaffolded Set base

Hand-rolling `add`/`has`/`all`/`values` on every collection drifts the contract.
Extend ONE shared base so the surface lives — and is enforced — in one place.

`commandments:scaffold` generates a `Set` base into your support namespace:

```php
namespace {{ namespace }};

/**
 * @template T
 */
abstract class Set
{
    /** @var list<T> */
    private array $items = [];

    /** @param T $item */
    public function add(mixed $item): static
    {
        if (! $this->has($item)) {
            $this->items[] = $item;
        }

        return $this;
    }

    /** @param T $item */
    public function has(mixed $item): bool
    {
        return in_array($item, $this->items, strict: true);
    }

    /** @return list<T> */
    public function all(): array
    {
        return $this->items;
    }
}
```

A concrete set extends it and narrows the type:

```php
final class EmitterSet extends Set
{
    public function add(Emitter $emitter): static
    {
        return parent::add($emitter);
    }
}
```

Extending the base marks the class as a `Set` (a base named `Set`), so
**SetReturnContract** enforces the total surface automatically — no keyed
`get(string)`, no `Option`/`?T` leak. If you find yourself wanting to override a
reader to look entries up BY KEY, stop: that is a Registry, not a set.
