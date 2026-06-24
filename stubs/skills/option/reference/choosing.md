# Option vs null vs Null Object vs throw

`Option<T>` is for **value-or-nothing where the nothing is legitimate**. It is
not the universal answer to every nullable — picking the wrong absence model is
itself a smell (`OptionDiscipline`). Decide before you reach for it.

## Decision table

| Situation | Model it as | Why |
|---|---|---|
| Absence is **legitimate** and the result is used at **several call sites**. | `Option<T>` | The empty case lives in the type once, not as a null check each caller can forget (`OptionDiscipline`). |
| Absence is legitimate but used at **one or two** nearby sites, handled obviously right there. | Plain `?T` | Wrapping a single local check in an Option buys nothing — the prophet stays silent below its caller threshold. |
| Absence means a **wiring/invariant bug** — the value should always be there. | `unwrap()` / a total method | Fail loud. Don't hand callers an empty they must handle for a case that means "broken". See the **invariants** skill. |
| The "absent" case has a sensible **do-nothing default behaviour**. | Null Object / empty instance | A `NullCallable`, empty collection, or `Thing::empty()` lets callers skip the branch entirely. See the **null-object** skill. |
| The value is **never** absent. | Return `T` directly | An Option that never returns `none()` is ceremony (`OptionDiscipline`). |

## Option vs a plain nullable

Both encode value-or-nothing; the difference is *who pays*. A `?T` return pushes
a hidden branch onto every caller — each grows a `=== null` / `?->` / `??`, and
forgetting one is a `TypeError` in production. `Option<T>` makes the branch
explicit and gives callers `map`/`unwrapOr`/`unwrap` to handle it without
re-deriving it.

- **Reach for Option** when the method *decides* nothingness (has an explicit
  `return null` alongside a value return) and has several callers.
- **Leave the plain nullable** for getters that return a nullable property, or
  passthroughs of someone else's nullable — those carry data, they don't decide
  absence. And leave it when there are only one or two callers handling the empty
  case locally.

## Option vs Null Object

A Null Object is an instance that **behaves** like "nothing" so callers never
branch at all — an empty collection you can still iterate, a `NullCallable` you
can still invoke, a `Thing::empty()` whose methods no-op. Prefer it over an
Option when the absent case has a coherent do-nothing behaviour, because it
removes the unwrap entirely.

```php
// Option — caller still has to handle the empty
$handlers->find($event)->inspect(fn ($h) => $h->handle($event));

// Null Object — no branch at all; the empty handler no-ops
$handlers->for($event)->handle($event);   // returns a NullHandler when unregistered
```

Reach for the Option when there is no sensible no-op behaviour and the caller
genuinely needs to know "present or absent". See the **null-object** skill for
the full Null-Object-vs-Option call.

## Option vs throw

If "absent" means a programming error rather than a valid outcome, don't model
it as an absence at all — throw. A registry `get()` that misses, a config key
that *must* exist, a relationship that the schema guarantees: a miss is a bug, so
`unwrap()` (or a method that simply returns `T`) is correct, not `Option`.
Use a domain-named exception built lazily on the empty path:

```php
$user = $this->find($id)->unwrapOrElse(fn () => throw UserNotFound::withId($id));
```

The **named-exceptions** skill covers owning the message on the exception; the
**invariants** skill covers telling genuine absence from an invariant violation.

## When to reach for Option

- The empty case is a **real, expected outcome** (not-found, no-match,
  optional-field) AND it flows to multiple callers or onward through
  `map`/`andThen`.

## When to leave it

- The value is always present → return `T`.
- Absence is a bug → throw.
- Absence has a do-nothing behaviour → Null Object / empty instance.
- A single local nullable handled right where it occurs → plain `?T`.
