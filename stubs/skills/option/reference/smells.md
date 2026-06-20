# Option smells — bad → good

Each section is one prophet's finding turned into the fix. The smell is almost
always "you paid for the Option, then threw it away" — converting it back to a
nullable, re-deriving emptiness by hand, or wrapping it in ceremony.

## `getOr(null)` — unwrap to null, then null-check (`NoOptionToNull`)

`getOr(null)` converts `Option<T>` back to `T|null`, and the code right after it
goes back to `?->` / `=== null`. The Option was pointless.

```php
// Bad
$input = $this->inputByName($port)->getOr(null);
if ($input?->socketType() === SocketType::Bag) { … }

// Good — stay inside the Option
$this->inputByName($port)->tap(fn (Input $i) => …);                  // act when present
$type = $this->inputByName($port)->transform(fn (Input $i) => $i->socketType());
$input = $this->inputByName($port)->getOrThrow();                   // absence is a bug
$input = $this->inputByName($port)->getOr(Input::empty());          // a REAL default
```

| Reach for the fix when | Leave it when |
|---|---|
| You unwrap with `getOr(null)` and the next lines null-check the result. | The value-or-null is **carried** straight to a sink that accepts null — a nullable argument, a `return` matching the method's own `?T` contract, or a factory arrow whose null means "no match". The smell is unwrap-THEN-check, not unwrap-and-hand-off. |

`getOr()` must carry a **real** default. If you find yourself null-checking the
result, use `transform()` / `tap()` / `getOrThrow()` instead.

## `?? null` — coalesce to the thing it already returns (`NoNullCoalesceToNull`)

`$x ?? null` falls back to null, which is what `??` already yields when the left
side is null. It is `$x`, longer. **[AUTO-FIXABLE]** — `repent` strips it.

```php
// Bad
$name = $this->label() ?? null;     // no-op — it is $this->label()
return $this->build() ?? null;      // no-op — it is $this->build()

// Good
$name = $this->label();
return $this->build();
```

| Reach for the fix when | Leave it when |
|---|---|
| The left side is **guaranteed defined** (a call return, `new`, a literal/constant) and the right side is the `null` literal. | The right side is a real fallback (not `null`), OR the left side is an array access / property / bare variable where `?? null` suppresses an undefined-key / uninitialized notice (load-bearing). |

If the value can legitimately be absent and you need a default, give a real one
(or `T_X::coalesce(...)` for a nullable typed value) — not `?? null`.

## Option in a union — two encodings stacked (`NoOptionInUnion`)

`Option` *is* the value-or-nothing type. Unioning it undoes that: `Option | null`
stacks two "nothing"s; `Option | string` is "sometimes wrapped, sometimes not".

```php
// Bad
Option | string | null $elementType = null,
public function find(): Option | null { … }

// Good — keep the Option, move the alternatives INTO the generic
/** @var Option<string> */
Option $elementType,

/** @return Option<array|string> */
public function rule(): Option { … }
```

Do **not** "fix" it by deleting the Option and widening to a raw union — that is
backwards (now it is an un-modelled value-or-nothing *and* a fat union). The
answer is `Option<...>`: the `null` becomes the Option's absence, the rest its
generic. A plain `?Thing` is only right when you never wanted an Option here.

## Guard-then-unwrap — re-deriving emptiness by hand

`Option` ships `getOr`/`transform`/`tap`/`getOrThrow` precisely so callers never
ask "are you empty?" and then unwrap.

```php
// Bad — UnwrapOptionWithGuard
if ($node->isEmpty()) {
    return ControlSockets::OUT;
}
$value = $node->getOrThrow();
return $value->sockets();

// Good
return $node->transform(fn ($v) => $v->sockets())->getOr(ControlSockets::OUT);
```

```php
// Bad — PreferOptionChainOverGuard (empty branch returns / throws)
$workflow = $this->findWorkflow($id);
if ($workflow->isEmpty()) {
    return $this->respondError(sprintf('No workflow found with id "%s".', $id));
}
return $this->report($workflow->getOrThrow());

// Good — present branch transforms, empty branch is orElse / getOrThrow
return $this->findWorkflow($id)
    ->transform(fn (Workflow $w) => $this->report($w))
    ->orElse(fn () => $this->respondError(sprintf('No workflow found with id "%s".', $id)))
    ->getOrThrow();
```

| Reach for the fix when | Leave it when |
|---|---|
| An `if ($o->isEmpty()) { return/continue/throw }` guard is immediately followed by `$o->getOrThrow()` on the same Option. Empty-returns-a-value → `orElse`; empty-throws → push the throw into `getOrThrow(fn () => …)`. | The guard body does real work beyond an early exit (logs, side effects), the present branch is large/multi-statement with several early exits, or the guard and unwrap act on different variables. |

## `transform(...)->getOr(none())` — Option&lt;Option&gt; flattened by hand (`PreferAndThen`)

If the callback returns an Option, `transform` nests it; the only reason to chase
it with `->getOr(Option::none())` is to peel the extra layer. **[AUTO-FIXABLE]**.

```php
// Bad
return $graph->nodeById($id)
    ->transform(fn ($n) => $this->descriptors->forNode($n))
    ->getOr(Option::none());

// Good
return $graph->nodeById($id)
    ->andThen(fn ($n) => $this->descriptors->forNode($n));
```

The `none()` default is the tell. Leave it only when the `getOr()` default is a
real value, or the callback genuinely returns a plain value.

## Redundant `orElse` wrap (`NoRedundantOrElseWrap`)

`orElse` already lifts a bare value into an Option (`null` → `none`), so wrapping
the alternative in `Option::some(...)` / `Option::make(...)` is dead boilerplate.
**[AUTO-FIXABLE]**.

```php
// Bad
->orElse(fn () => Option::some($this->respondError('not found')))
// Good
->orElse(fn () => $this->respondError('not found'))
```

Leave the explicit wrap only for `Option::none()`, a conditionally-built Option
(`when`/`someWhen`/another chain), or the rare `Option::some(null)` where you
truly need present-but-null (`orElse`'s `make()` lift would collapse null to
`none`).

## Hand-rolled some/none branch (`PreferOptionFactory`)

A `?:` or `if/else` whose two arms are exactly `Option::some(...)` and
`Option::none()` is the open-coded version of a named factory. See the factory
table in `api.md` — `make` for a null check, `find` for a key probe, `someWhen`
for a condition (`someWhenNot` when `some` is the false branch). Leave it when
the branches wrap different values or do other work.

## Option-as-ceremony (`NoOptionOveruse`)

A method typed `: Option` that never returns `none()` lies — every caller pays
the unwrap cost for an absence that cannot happen. See `choosing.md`: if the
value is never absent, return it directly (or throw when it genuinely cannot be
produced).
