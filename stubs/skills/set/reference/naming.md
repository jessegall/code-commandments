# Naming honesty — Set vs Registry

Both a Set and a Registry are collections you PUT things into. The single question
that tells them apart:

> **Do callers look entries up BY KEY?**

| You … | It is a … | Name it | Reader/getter |
|---|---|---|---|
| `add` items, ask `has(item)`, iterate `all()`/`values()` | **Set** | `*Set` / `*Collection` | membership (`bool`) + bulk iteration |
| `register(key, value)`, then `get(key)` the value back | **Registry** | `*Registry` / `*Map` | keyed lookup (`get(): T` or throw) |

A class with the set shape (you `add`/append and only ever iterate, no keyed
value lookup) but a vaguer name hides its contract: a reader cannot tell whether
to look up by key (they can't — there is no keyed `get`), and the marker-driven
**SetReturnContract** rule cannot see it to enforce the total surface.

```php
// Shape says "set" (add + iterate, no keyed get) — name says nothing.
class EmitterStuff
{
    private array $items = [];
    public function add(Emitter $e): void { $this->items[] = $e; }
    public function all(): array { return $this->items; }
}

// Honest: the name advertises the contract, and extending the base enforces it.
final class EmitterSet extends Set { … }
```

The near misses:

- you look entries up BY KEY (`get(string): T`) → it is a **Registry**, not a set
  — see the `registry` skill;
- it computes/derives values on demand and owns no collection → a `*Resolver` /
  `*Factory`.

Marking the class (a `*Set` name, a `Set` base/interface, or `#[Set]`) is the
opt-in to strict enforcement: once marked, SetReturnContract holds the surface to
add + membership + total iteration.
