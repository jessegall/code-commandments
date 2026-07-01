<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend\Concurrent;

use JesseGall\CodeCommandments\Skills\Backend\Exceptions;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class ConcurrentState extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/concurrent-state',
            tier: Tier::KeepInMind,
            order: 14,
        );
    }

    public function title(): string
    {
        return "Concurrent state — a plain object behind `::for()`";
    }

    public function trigger(): string
    {
        return "How to model state shared across processes (web request ↔ queue worker ↔ cron) — a plain domain class with behaviour methods plus a static `::for(\$id): Concurrent<self>` factory that owns the cache key, default, and TTL (jessegall/concurrent). Read this FIRST whenever you reach for `Cache::get/put` with a hand-built key, a static/global for cross-request state, a polled status / progress / counter / pointer shared between a request and a worker, or `new Concurrent(...)`.";
    }

    public function intro(): string
    {
        return "State shared across processes is not a pile of `Cache::get`/`put` calls with a key you reinvent at every
site. It is a **domain object** with behaviour methods, handed to you thread-safe by one factory.";
    }

    public function summary(): string
    {
        return "state shared across requests/workers (`::for(\$id): Concurrent<self>`).";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
When a web request, a queue worker, and a cron job all touch the same state (a turn's status the frontend
polls, a run's progress timeline, a counter, a claimed "current" pointer), the naive version scatters
cache keys, forgets to lock, and races on every read-modify-write. `jessegall/concurrent` wraps the value
in a `Concurrent<T>` proxy: **reads don't lock; a method call or write locks, mutates, and persists —
atomically.** Your job is to keep the *domain* clean and let one factory hide the proxy.

The house pattern (and the one you want): the wrapped value is a **plain class** — `extends Concurrent` is
*not* used — and a static **`::for($identity): Concurrent<self>`** factory returns the thread-safe handle.

### Why this shape

- **The domain class stays a plain object.** Private state + behaviour methods (`markPending`,
  `snapshot`). Zero cache/lock plumbing in the methods — you call `->markPending()`, never
  `->set('status', …)`. The concurrency concern lives only in `::for()`.
- **`::for($id)` is the single source of construction + keying.** Key string, default, TTL — one place.
  No cache key reinvented across call sites (the whole reason the package exists). It reads as the same
  `::for(...)` named-factory vocabulary used elsewhere — "the shared handle *for* this identity".
- **Composition, not `extends Concurrent`.** Returning `Concurrent<self>` instead of subclassing keeps
  the domain class free of the proxy's API (no method-name collision, independently unit-testable as a
  plain object). `@return Concurrent<self>` + the proxy's `@mixin TValue` still give callers full
  completion on the domain methods through the wrapper.

### Mechanics you must respect

- **A method call on the handle is an atomic write** — lock → run the method on the wrapped value → write
  back → release. Locks are re-entrant, so nested writes in one method share one lock.
- **Reads don't lock**: `$c()` (get), property reads, `isset`, and methods marked **read-only**. Mark
  every pure accessor (like `snapshot()`) with `#[ReadonlyMethod]` so hot polling doesn't take a
  write-lock — mutating from a read-only method throws, catching mistakes early.
- **Group a read-modify-write into one atomic step** with a callback: `$c(fn (Cart &$d) => $d->items[] =
  $x)` (by-ref), `$c(fn ($v) => $v + 1)` (transform), or `$c(function () { $this->… })` (bound).
- **Two shapes look atomic and are not** — never do them on the handle:
  - `$c->count++` → read-add-write across three un-held locks; increments are lost. Use a callback (or
    `ConcurrentCounter`).
  - `$c->items[] = $x` → PHP appends to a copy; the cache never sees it. Use a by-ref callback.
- For plain shared structures, the package ships `ConcurrentMap / Set / Counter / Queue / List` — reach
  for those instead of hand-rolling a wrapped array.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            Exceptions::class => "\"the handle/instance *for* this identity\".",
        ];
    }
}
