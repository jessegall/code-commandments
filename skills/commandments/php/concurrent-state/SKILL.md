---
name: commandments-php-concurrent-state
description: "How to model state shared across processes (web request ↔ queue worker ↔ cron) — a plain domain class with behaviour methods plus a static `::for($id): Concurrent<self>` factory that owns the cache key, default, and TTL (jessegall/concurrent). Read this FIRST whenever you reach for `Cache::get/put` with a hand-built key, a static/global for cross-request state, a polled status / progress / counter / pointer shared between a request and a worker, or `new Concurrent(...)`."
---

# Concurrent state — a plain object behind `::for()`

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> State that several processes share (a web request, a queue worker, a cron job) is a domain object with methods,
> handed out thread-safe by one factory. It is not a pile of cache keys.
> Read this before you model state that is read and written from more than one process with `jessegall/concurrent`,
> and when a concurrent-state finding points here.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Keep the domain class plain, and let one `::for()` factory wrap it in a `Concurrent`.**

`jessegall/concurrent` wraps a value in a `Concurrent<T>` handle: a read does not lock; a method call or a write
locks, changes and saves in one step. Your part is to keep the two concerns apart:

- **The domain class is a plain object.** Private state and methods named for what happens (`advance()`,
  `markPending()`). No cache keys and no locks inside it, so it can be tested as an ordinary object.
- **One static `::for($identity)` factory returns the handle.** The key, the default value and the lifetime are
  written in that one place, never again at a call site.
- **Composition, not inheritance.** The class does not `extends Concurrent`. A subclass mixes the handle's own
  methods into the domain class and cannot be built without a cache.

Three things to respect when you use the handle:

- A method call on the handle is one atomic write. Mark a method that only reads with `#[ReadonlyMethod]`, so a
  page that polls it does not take the write lock.
- A read-modify-write is grouped in a callback: `$handle(fn (Cart &$cart) => $cart->items[] = $item)`.
- `$handle->count++` and `$handle->items[] = $x` look atomic and are not: the first loses increments, the second
  appends to a copy. Use a callback, or the package's `ConcurrentCounter`, `ConcurrentList`, `ConcurrentMap`.

## The sins

### concurrent-subclass

A class that `extends Concurrent`. The domain object and the locking handle become one class. Keep the class plain
and return a `Concurrent<self>` from a `::for()` factory.

```php
// Bad
final class LiveOrderStage extends Concurrent   // ← the domain class is the handle
{
    public function __construct(string $orderId)
    {
        parent::__construct(key: "order-stage:{$orderId}", default: 'received');
    }
}

// Good
final class LiveOrderStage
{
    private string $stage = 'received';

    /** @return Concurrent<self> */
    public static function for(string $orderId): Concurrent
    {
        return new Concurrent(key: "order-stage:{$orderId}", default: new self());   // ← the one place the key is written
    }

    public function advance(string $stage): void
    {
        $this->stage = $stage;
    }

    #[ReadonlyMethod]
    public function current(): string
    {
        return $this->stage;
    }
}

LiveOrderStage::for($order->id)->advance('packed');
```

## Commands

- `{{binary}} judge --skill=php/concurrent-state` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `concurrent-subclass`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`shared/exceptions`](../../shared/exceptions/SKILL.md) — "the handle/instance *for* this identity".
