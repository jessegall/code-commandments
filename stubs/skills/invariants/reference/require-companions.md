# Lookup companions: find / has / get and the …OrFail pair

A lookup API that can miss should expose the miss *honestly* — and offer both a
"maybe" path and a "must exist" path, so callers never have to catch an
exception just to discard it. The shape mirrors the scaffolded
`{{ namespace }}\Registry`.

| Method | Returns | Meaning |
|---|---|---|
| `find($key)` | `Option<T>` | A genuine value-or-nothing query — absence is a valid outcome. |
| `has($key)` | `bool` | Existence check companion to `get()`. |
| `get($key)` | `T` (or **throws**) | The invariant lookup — a miss is a wiring bug, so it fails loud. |
| `getById($id)` / `…OrFail($key)` | `T` (or **throws**) | "I already know this must exist" — named so the throw is expected at the call site. |

The rule: **the `find` variant returns an Option; the `get`/`…OrFail` variant
throws.** Give the caller the right tool so they never reach for a `try/catch`
to convert one into the other.

## The anti-pattern: catching a not-found to swallow it

The most common invariant violation in lookups is catching a `…NotFound`
exception and replacing it with a sentinel. That turns a loud, locatable bug
into a silent wrong-default every downstream caller then compensates for.

### Bad — swallow the miss into a sentinel
```php
try {
    $user = $this->users->getById($authenticatedUserId);
} catch (UserNotFoundException) {
    $user = null;
}
return $user?->displayName() ?? 'Guest';   // a corrupt session silently becomes "Guest"
```

### Good — the id is an invariant, so let it throw
```php
return $this->users->getById($authenticatedUserId)->displayName();
```

### Also good — if the absence is REAL, model it at the source (don't catch-to-discard)
```php
return $this->users->find($email)            // Option<User>
    ->map(fn (User $u) => $u->displayName())
    ->getOr('not found');
```

The fix is never "catch it more carefully" — it is to **pick the right
companion at the call site**: `getById()` when the key must exist (let it
throw), `find()` when it may legitimately miss (consume the Option).

## Decision table

| Situation | Reach for |
|---|---|
| The key was already established to exist (authenticated id, foreign key, required template) | `get()` / `getById()` / `…OrFail()` — let the miss throw |
| The key may legitimately be absent (optional lookup, probe, best-effort) | `find()` → `Option<T>`, consumed with `->map(...)->getOr(...)` |
| You're tempted to `try { get… } catch (…NotFound) { return null; }` | Stop — switch to `find()` and return the Option from the source instead |
| The catch does real recovery (retry, fallback fetch, log + rethrow, map to a domain exception) | Keep it — that is handling, not swallowing |

## When to reach for the throwing companion

- The looked-up thing is one the surrounding code already **requires** — its
  absence can only mean upstream corruption or a wiring bug.
- You want the failure to be loud and locatable at the exact lookup, not a
  silent wrong default that surfaces three layers away.

## When to leave it (keep the Option / keep the handling)

- The absence is genuinely expected here — a probe or best-effort lookup. Use
  `find()` and consume the `Option`; don't introduce a throw.
- The caught exception isn't actually a "must exist" miss, or the catch does
  **real** recovery (retry, fallback, logging then rethrow). That is legitimate
  handling — `NoSwallowedNotFound` only fires on a catch that does *nothing* but
  assign/return a sentinel (`null` / `false` / `[]`).
