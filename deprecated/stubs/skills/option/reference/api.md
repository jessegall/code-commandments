# Option&lt;T&gt; — the core API

`{{ namespace }}\Option` (the canonical one is `JesseGall\PhpTypes\Option`) holds
a value-or-nothing. Construct it, then handle the empty case explicitly. Unwrapping
and branching are normal — the type just made the absence impossible to forget.

## Construct it

| Factory | Use it when | Equivalent to |
|---|---|---|
| `Option::some($v)` | You have a value and it is definitely present. | — |
| `Option::none()` | The value is absent. | — |
| `Option::fromNullable($v)` | You have a `T\|null` and want to lift it. | `$v !== null ? some($v) : none()` |

```php
use {{ namespace }}\Option;

public function find(string $id): Option
{
    foreach ($this->rows as $row) {
        if ($row->id === $id) {
            return Option::some($row);
        }
    }

    return Option::none();
}
```

## Query it

| Method | Returns | |
|---|---|---|
| `isSome()` / `isNone()` | `bool` | Is a value present / absent. |
| `isSomeAnd(fn ($v) => …)` | `bool` | Present AND the value passes the predicate. |
| `isNoneOr(fn ($v) => …)` | `bool` | Absent, OR the value passes the predicate. |

## Get the value out

| Method | Returns | Use it when |
|---|---|---|
| `unwrap()` | `T` | Absence is a bug — throw `UnwrapException` if empty. |
| `expect($message)` | `T` | Same, with your own message documenting the invariant. |
| `unwrapOr($default)` | `T` | You have a **real** default. Never pass `null` (see `smells.md`). |
| `unwrapOrElse(fn () => …)` | `T` | The default is expensive / only valid on the empty path. |
| `toNullable()` | `T\|null` | A genuine `?T` boundary still speaks nullable. |

```php
$row = $this->find($id)->expect("row {$id} must exist");   // absence is a bug
$row = $this->find($id)->unwrapOr(Row::blank());           // a real default
```

## Transform & handle

| Method | Shape | Use it when |
|---|---|---|
| `map(fn ($v) => …)` | value → value | Map the present value; `none` stays `none`. |
| `mapOr($default, fn ($v) => …)` | value → value | Map present, else the default value. |
| `mapOrElse(fn () => …, fn ($v) => …)` | — | Map present, else compute a default (the `match { some, none }` fold). |
| `filter(fn ($v) => …)` | value → bool | Drop to `none` unless the predicate holds. |
| `inspect(fn ($v) => …)` | side effect | Run a side effect on the present value, return the same Option. |
| `andThen(fn ($v) => …)` | value → **Option** | The callback returns an Option — `andThen` flattens (no `Option<Option>`). |
| `or($other)` / `orElse(fn () => …)` | alternative | This Option if present, else `$other` / the callback's Option. |
| `and($other)` | — | `$other` if this is present, else `none`. |
| `xor($other)` / `zip($other)` / `flatten()` | — | Exactly-one / pair-into-tuple / collapse `Option<Option>`. |

```php
// Map, then fall back — branching on absence is fine, this is just terser
$label = $this->find($id)
    ->map(fn (Row $r) => $r->label)
    ->unwrapOr('unknown');

// andThen flattens when the callback itself returns an Option
return $this->nodeById($id)
    ->andThen(fn (Node $n) => $this->descriptorFor($n));   // Option, not Option<Option>
```

## When to reach for it

- The value-or-nothing decision spans **more than one or two callers** — model it
  once in the type instead of repeating a null check at each site.

## When to leave it

- The method **always** has a value — return `T` directly (or throw if it
  genuinely cannot be produced). An Option that never returns `none()` is ceremony
  (`OptionDiscipline`).
- There are only one or two callers and the empty case is handled obviously right
  where it occurs — wrapping a single nearby check buys nothing.
