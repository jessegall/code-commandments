---
name: absence
description: Decide how a value that might not be there is modelled — throw vs Option vs empty vs Null Object vs a plain nullable. Read this FIRST whenever you are about to write a `?T` / `T | null` return or property, `return null`, an `Option`, a `?->` / `=== null` / `?? default`, or whenever you are unsure if something "can be missing". Answers when it is OK to return null and when it is a bug.
---

# Absence — model "might not be there" honestly

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> `null` is not a way to model absence. It is the *absence of a decision* about absence. Make the decision.

## The principle

A value that might not be there forces a question on every reader: *what does "not there" mean here?*
Answer it **once, in the type**, so no reader has to guess. There are four real kinds of absence and one
honest use of `null` — pick deliberately; never default to a bare nullable.

This is [`fix-at-the-source`](../fix-at-the-source/SKILL.md) applied to absence: model the absence **where
the value is born**, not at every caller that de-nulls it. If several callers each `=== null` the same
value, that is the producer's type lying — fix the producer.

## The decision: what kind of absence is this?

Ask these **in order** and stop at the first yes.

1. **Can it actually be absent at all — or is "missing" a broken state?**
   If the value *must* exist for the program to be correct (an engine part, a registered handler, a
   config the app can't run without), then absence is an **invariant violation, not a value**. → **Throw a
   named exception.** Do not return null/Option for it. (Reserve `Option` for *genuine* domain absence,
   never for an invariant the code relies on. Prefer a registry's `get()` (return-or-throw) over `find()`
   wherever presence is assumed.)

2. **Does "nothing" have a natural empty form?**
   A list with no elements → an **empty collection**, never null. A behaviour with nothing to do → a
   **Null Object** (a no-op implementation), never null. → Return the empty/identity value. The caller
   loops/calls it with zero special-casing.

3. **Is it a genuine "look for it; it may legitimately miss"?**
   A find that can honestly come back empty, where the caller must consciously handle both arms. →
   **`Option<T>`.** Construct with `Option::some()` / `Option::none()` / `Option::fromNullable()`; consume
   with `unwrapOr()` / `match()` / `map()` — branching on an Option is normal, that's how you use one.

   **Option vs. a bare null — decide on *blast radius* (how far the value travels).** If the maybe-missing
   value flows through more than one consumer, it is an **`Option`**: the absence rides *in the type* and
   every consumer is forced to handle it — you can't thread a raw null outward and forget one site. If it
   is a single **local lookup checked right where it's produced** (one caller, one `=== null`, done), a
   bare `null` is honest — an `Option` there is ceremony. The smell the tools flag: a `?T` that *travels*
   (every caller re-`=== null`s / `?->`s it). That null should have been a value, a throw, or an `Option`.

4. **Is it a genuinely optional *input* the caller may omit?**
   An optional parameter or config value. → Prefer a **Null Object default** or a real default value in the
   signature over a nullable normalised in the body. A bare `?T $x = null` that the body immediately
   `??=`-fills is the smell; bake the default into the signature.

5. **Otherwise** — you've reached the one honest `null` (below).

## When `null` IS OK

Narrow, and almost always on an **input or a framework seam**, never on a domain return:

- A framework / SDK hands you `null` (a nullable Eloquent relation, a `config()` miss). Tolerated at the
  **seam** — wrap it promptly with `Option::fromNullable($x)` and stop the null at the door; don't thread
  it inward.
- A truly optional value whose absence is itself meaningful *and* has no empty/Null-Object form, where
  `Option` would be ceremony for a one-caller local. Keep it local and obvious.

If you can't point at one of those, you do **not** have an honest null — go back to the decision.

## When `null` is a BUG

- A **return** typed `?T` that callers de-null (`=== null`, `?->`, `?? $d`). → Option, empty, or throw —
  decide at the source (step 1–3), don't make every caller decide.
- `return null` for "not found" on something that **must** exist. → Throw (step 1).
- `?? ''` / `?? 0` / `?? []` to fill a **required** non-nullable slot. → A manufactured fake value that
  drops the absence signal. Throw, or make the slot honestly optional. (See `fix-at-the-source`.)
- An **`Option` used as a nullable**: `Option | null`, `?Option`, `unwrapOr(null)`, or an Option whose
  every return is `some()` (never `none()`). → That's a null wearing an Option costume; pick one model.

## Bad → good

```php
// Bad — a nullable return every caller must de-null
public function find(string $id): Row | null
{
    foreach ($this->rows as $row)
    {
        if ($row->id === $id) { return $row; }
    }

    return null;
}

// Good (genuine miss) — the absence is in the type, impossible to forget
public function find(string $id): Option
{
    foreach ($this->rows as $row)
    {
        if ($row->id === $id) { return Option::some($row); }
    }

    return Option::none();
}

// Good (invariant) — presence is assumed; missing means broken state
public function get(string $id): Row
{
    return $this->find($id)->unwrapOr(throw RowNotFoundException::forId($id));
}
```

```php
// Bad — null for "no items", so every caller guards before iterating
public function tags(): array | null { return $this->tags ?: null; }

// Good — empty collection: callers just iterate
public function tags(): array { return $this->tags; }   // [] when there are none
```

```php
// Bad — nullable callback normalised in the body
public function __construct(private \Closure | null $onDone = null) {}
// ... later: ($this->onDone ?? fn () => null)();

// Good — a Null Object default; no branch, ever
public function __construct(private \Closure $onDone = new NullCallable) {}
// ... later: ($this->onDone)();
```

## Checklist

```
Absence
- [ ] I asked "is missing a broken state?" first — if yes, it THROWS, not returns null/Option.
- [ ] A "nothing" with an empty form returns the empty collection / Null Object, not null.
- [ ] A genuine miss is Option<T>; absence lives in the type, not in every caller.
- [ ] Any surviving null is an optional INPUT or a framework seam — not a domain return.
- [ ] No `?? <empty literal>` filling a required slot; no Option-as-nullable (`unwrapOr(null)`, `?Option`).
```

## Relationship to the other skills

- The parent move is [`fix-at-the-source`](../fix-at-the-source/SKILL.md): decide absence at the producer,
  not at the callers.
- "Missing = broken state → throw a *named* exception" hands off to the exceptions skill for *how* to throw.
- A required slot filled with `?? ''` is a boundary lie — that belongs to the typed-boundary / parse story.
