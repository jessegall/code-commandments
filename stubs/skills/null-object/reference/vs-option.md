# Null Object / empty instance vs Option vs null vs throw

Four ways to model "there might be nothing here". Pick by what the caller needs
to *do* with the absence.

## Decision table

| Situation | Use | Why |
|---|---|---|
| Absent case has a sensible **no-op behaviour** (do-nothing observer/logger/callback) | **Null Object** (`new NullObserver()`, `{{ namespace }}\NullCallable`) | Caller calls through with no guard; the absence behaves. |
| T is **collection-like** and callers iterate / `->all()` / `count()` | **empty instance** (`[]`, `T::empty()`, `new T()`) | Empty IS the absence; no `| null`, no null-guard. |
| Absence is a **value** the caller must read and may branch on, but is not an error | **`Option<T>`** (`{{ namespace }}\Option`) | A typed present-or-absent; caller uses `getOr()`/`transform()`/`andThen()` — see the `commandments-option` skill. |
| Caller must **distinguish ABSENT from EMPTY** (cache miss vs empty result, 404 vs found-but-empty) | **null** or **`Option`** | An empty value can't carry "I looked and there was nothing" vs "I didn't find it". |
| Absence is an **invariant violation** (this should always exist) | **throw** a named exception | Fail loud — see the `commandments-named-exceptions` / `commandments-invariants` skills. |

## Null Object / empty vs Option — the core split

Reach for a **Null Object / empty instance** when the caller never needs to
*know* the value was absent — it just needs the thing to behave (no-op) or be
iterable (empty). The absence is **invisible** and that is correct.

Reach for an **`Option<T>`** when the caller legitimately *reads* the absence and
may act on it — render a placeholder, pick a fallback, short-circuit a chain. The
absence is **a value** the caller handles explicitly.

```php
// Null Object: caller doesn't care, just calls.
$this->observer->completed($cmd);            // no-op if none

// Empty instance: caller iterates, doesn't care.
foreach ($repo->rows() as $row) { /* ... */ }

// Option: caller reads the absence and decides.
$repo->find($id)
    ->transform(fn (User $u) => $u->name)
    ->getOr('(unknown)');
```

## The load-bearing carve-out: ABSENT vs EMPTY

Collapsing `| null` to an empty instance is **only** safe when "no value" and
"empty value" mean the same thing to every caller. The moment one caller branches
on the null, an empty value silently changes behaviour:

```php
// Cache: null = miss (go compute), [] = a cached empty result (don't).
public function cached(string $key): ?Collection
{
    return $this->store->has($key) ? $this->store->get($key) : null;
}
```

Here null carries "I have nothing cached" — distinct from "I cached an empty
list". Returning an empty collection would skip the recompute. **Keep the null
(or model it as `Option`).** `PreferEmptyOverNull` honours this: it already skips
a method whose callers branch on the null, and skips private lazy-init memo
properties.

## When to reach for it

- Absent → no-op: **Null Object**.
- Absent → iterate nothing: **empty instance**, drop the `| null`.
- Absent → a value the caller reads/branches on, not an error: **`Option<T>`**.

## When to leave it

- Caller distinguishes **absent from empty**: keep **null** / use **`Option`**.
- Absence is an **error / invariant violation**: **throw a named exception**, do
  not hand back an empty no-op that hides the bug.
