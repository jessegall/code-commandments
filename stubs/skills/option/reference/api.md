# Option&lt;T&gt; — the core API

The scaffolded `{{ namespace }}\Option` holds a value-or-nothing. You construct
it through a named factory and then *stay inside it* — every combinator returns
either an `Option` or the unwrapped `T`, never a `T|null`.

## Construct it (static factories)

| Factory | Use it when | Equivalent to |
|---|---|---|
| `Option::some($v)` | You have a value and it is definitely present. | — |
| `Option::none()` | The value is absent. | — |
| `Option::make($v)` | You have a `T\|null` and want to lift it. | `$v !== null ? some($v) : none()` |
| `Option::find($array, $key)` | Looking a value up in a dictionary. | `make($array[$key] ?? null)` |
| `Option::first($items, $predicate)` | First match in an iterable. | a `foreach` returning `some`/`none` |
| `Option::coalesce($a, $b, …)` | First non-null of several candidates. | `$a ?? $b ?? … ` then lift |
| `Option::someWhen($cond, $v)` | A plain condition decides presence; wraps a bare value OR a factory. | `$cond ? some($v) : none()` |
| `Option::someWhenNot($cond, $v)` | Same, but present on the **false** branch. | `$cond ? none() : some($v)` |
| `Option::when($cond, fn () => $opt)` | The factory already returns an Option. | `$cond ? $opt : none()` |

`someWhen`/`someWhenNot` take a bare value OR a factory: a **callable** is invoked
only when the condition holds (so a value that depends on the condition — or an
expensive one — isn't built otherwise); anything else is wrapped as-is. Use the
**eager** form for a value independent of the condition
(`Option::someWhen($flag, UiIntent::fitView())`); use the **lazy closure** form
when the value depends on the condition holding — e.g. it dereferences something
the condition just proved exists (`Option::someWhen($reg->has($id), fn () => $reg->node($id))`).

Use `someWhen`, **not** `when`, to wrap a value — `when($c, $f)` returns `$f()`
verbatim (the factory must already hand back an Option), while `someWhen` does the
`some()` wrap for you.

```php
use {{ namespace }}\Option;

// Bad — a hand-rolled some/none branch a reader must re-derive
return $value !== null ? Option::some($value) : Option::none();
return isset($items[$key]) ? Option::some($items[$key]) : Option::none();

// Good — the named factory whose shape matches the branch
return Option::make($value);            // a null check
return Option::find($items, $key);      // a key probe
return Option::someWhen($reg->has($id), fn () => $reg->node($id));  // a condition
```

## Unwrap it (terminal operations)

| Method | Returns | Use it when |
|---|---|---|
| `getOrThrow()` | `T` | Absence is a bug — fail loud. Pass `fn () => new DomainException(...)` to throw a domain error built only on the empty path. |
| `getOr($default)` | `T` | You have a **real** default. Never pass `null` (see `smells.md`). |
| `hasValue()` / `isEmpty()` | `bool` | A boolean is genuinely what you need — not as a prelude to a manual unwrap. |

```php
// Absence is a wiring bug — require it
$input = $this->inputByName($port)->getOrThrow();

// A genuine default — never null
$input = $this->inputByName($port)->getOr(Input::empty());

// A domain error, built lazily on the empty path only
$user = $this->find($id)->getOrThrow(fn () => UserNotFound::withId($id));
```

## Transform it (stay inside the Option)

| Method | Shape | Use it when |
|---|---|---|
| `transform(fn ($v) => …)` | value → value | Mapping the present value to another value; `none` stays `none`. (Named `transform`, not `map`, because an Option holds one value.) |
| `andThen(fn ($v) => …)` | value → **Option** | The callback itself returns an Option — `andThen` flattens, so you never get `Option<Option>`. |
| `filter(fn ($v) => …)` | value → bool | Drop the value to `none` unless the predicate holds. |
| `tap(fn ($v) => …)` | side effect | Run a side effect on the present value and keep the chain going. |
| `orElse(fn () => …)` | lazy alternative | Fall back to another Option **or a bare value** (auto-lifted) when empty. |
| `or($otherOption)` | eager alternative | Same, but the alternative is already built. |

`transform` vs `andThen` is the one that bites: if the callback returns an
Option, use `andThen`. Following a `transform` with `->getOr(Option::none())` to
flatten by hand is exactly what `andThen` is for.

```php
// Bad — transform nests, then you unwrap the extra layer by hand
return $graph->nodeById($id)
    ->transform(fn ($n) => $this->descriptors->forNode($n))   // Option<Option>
    ->getOr(Option::none());

// Good — andThen flattens in one step
return $graph->nodeById($id)
    ->andThen(fn ($n) => $this->descriptors->forNode($n));
```

`orElse` auto-lifts a bare value (`null` → `none`), so never hand-wrap the
alternative:

```php
// Bad
->orElse(fn () => Option::some($this->respondError('not found')))
// Good
->orElse(fn () => $this->respondError('not found'))
```

## Which combinator replaces which guard

| You wrote | Reach for |
|---|---|
| `if ($o->isEmpty()) return $d; $x = $o->getOrThrow();` | `$o->getOr($d)` |
| `if ($o->isEmpty()) { … } else { use $o->getOrThrow() }` (transform) | `$o->transform(present)->orElse(empty)` |
| `if ($o->isEmpty()) throw …; $x = $o->getOrThrow();` | `$o->getOrThrow(fn () => $exception)` |
| `if (! $o->isEmpty()) { sideEffect($o->getOrThrow()); }` | `$o->tap(fn ($v) => sideEffect($v))` |

## When to reach for it

- The value-or-nothing decision spans **more than one or two callers** — model
  it once in the type instead of repeating a null check at each site.
- You are unwrapping — pick the combinator that matches intent (`getOrThrow`
  when absence is a bug, `getOr($real)` for a default, `transform`/`andThen` to
  keep computing, `tap` for a side effect) rather than `isEmpty()`-then-unwrap.

## When to leave it

- The method **always** has a value — return `T` directly (or throw if it
  genuinely cannot be produced). An Option that never returns `none()` is
  ceremony (`NoOptionOveruse`).
- There are only one or two callers and the empty case is handled obviously
  right where it occurs — wrapping a single nearby check buys nothing
  (`PreferOptionOverNull` stays silent below its caller threshold).
