# The set contract — membership + total iteration, no keys

A set answers two questions and no more:

- **Is this item in?** — `has(item): bool` (and maybe `contains(item): bool`).
- **What is in it?** — `all(): array` / `values(): list` — TOTAL; it returns
  everything it holds.

It is filled with `add()` (append or dedup-by-identity) and that is the whole
surface. Two things are foreign to it.

## No keyed value lookup

```php
// BAD — a keyed lookup. If you can ask "the value FOR this key", it is a Registry.
public function get(string $key): Node
{
    return $this->items[$key];
}
```

A set has no keys to look values up by. The moment you need
`get(string $key): T`, you wanted a **Registry** — name it `*Registry` and let the
registry contract govern it (see the `registry` skill). On a class marked `*Set`,
a keyed `get(string): T` is the breach SetReturnContract reports.

## No absence across the boundary

```php
// BAD — a set is total; it does not hand a maybe out for callers to unwrap.
public function first(): Option { … }
public function find(string $type): ?Node { … }
```

Membership is a `bool`; iteration returns everything. There is no "the value is
missing" case to push onto callers — that is what an `Option`/`?T` return models,
and it does not belong on a set's surface. (A NULLABLE *finder* named `find*`/
`try*`/`*OrNull` is the one exception the rule leaves alone — but a keyed finder
is really a Registry concern.)

## Good — the whole surface

```php
public function add(Node $node): static
{
    if (! $this->has($node)) {
        $this->items[] = $node;
    }

    return $this;
}

public function has(Node $node): bool
{
    return in_array($node, $this->items, strict: true);
}

/** @return list<Node> */
public function all(): array
{
    return $this->items;
}
```
