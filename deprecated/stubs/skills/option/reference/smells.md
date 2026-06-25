# Option smells — bad → good

Each section is one prophet's finding turned into the fix. The rule underneath
them all: **model absence exactly when absence is real.** Unwrapping or branching
on an Option is normal and never a smell — only a type that misrepresents absence
is (a bare null callers juggle, or an Option that is never empty).

## Decides null → model the absence (`OptionDiscipline`, adopt)

A method that returns a value OR `null` from its body pushes a hidden branch onto
every caller. When several callers each `=== null` it, make the empty case a type.

```php
// Bad — every caller grows its own null check
public function find(string $id): Row|null
{
    foreach ($this->rows as $row) {
        if ($row->id === $id) { return $row; }
    }

    return null;
}

// Good — the absence is explicit and impossible to forget
public function find(string $id): Option
{
    foreach ($this->rows as $row) {
        if ($row->id === $id) { return Option::some($row); }
    }

    return Option::none();
}
```

| Reach for the fix when | Leave it when |
|---|---|
| Several callers branch on the null (`=== null`, `?->`, `??`). | Only one or two callers, the empty case is local and obvious, or absence is genuinely exceptional — then throw, don't wrap. |

## Always-some Option → return the value (`OptionDiscipline`, overuse)

A method typed `: Option` whose every return is `Option::some(...)` is never empty
— every caller pays an unwrap for an absence that cannot happen.

```php
// Bad — never none(), so the Option lies
public function current(): Option { return Option::some($this->value); }

// Good — there is always a value
public function current(): Value { return $this->value; }
```

If the value genuinely cannot be produced on some path, return `none()` there (the
Option is honest) — or throw if absence is a bug. Don't add a fake `none()` to
satisfy the rule.

## Wrap then unwrap → use the value (`OptionDiscipline`, overuse)

`Option::some($x)->unwrap()` builds an Option only to unbox it in the same breath.

```php
// Bad
$value = Option::some($this->compute())->unwrap();

// Good
$value = $this->compute();
```

## `unwrapOr(null)` — unwrap to null, then null-check (`NoOptionToNull`)

`unwrapOr(null)` converts `Option<T>` back to `T|null`, and the code right after
goes back to `?->` / `=== null`. The Option was pointless.

```php
// Bad
$input = $this->inputByName($port)->unwrapOr(null);
if ($input?->socketType() === SocketType::Bag) { … }

// Good — handle the absence on the Option
$type = $this->inputByName($port)->map(fn (Input $i) => $i->socketType());
$input = $this->inputByName($port)->expect('input must exist');   // absence is a bug
$input = $this->inputByName($port)->unwrapOr(Input::empty());      // a REAL default
```

| Reach for the fix when | Leave it when |
|---|---|
| You unwrap with `unwrapOr(null)` and the next lines null-check the result. | A genuine `?T` boundary: `toNullable()` (or `unwrapOr(null)`) feeds a nullable argument or a `return` matching the method's own `?T` contract. The smell is unwrap-THEN-check, not unwrap-and-hand-off. |

## `?? null` — coalesce to the thing it already returns (`NoNullCoalesceToNull`)

`$x ?? null` falls back to null, which is what `??` already yields. It is `$x`,
longer. **[AUTO-FIXABLE]**.

```php
// Bad                              // Good
$name = $this->label() ?? null;     $name = $this->label();
return $this->build() ?? null;      return $this->build();
```

| Reach for the fix when | Leave it when |
|---|---|
| The left side is **guaranteed defined** (a call return, `new`, a literal/constant) and the right is the `null` literal. | The right side is a real fallback, OR the left is an array access / property / bare variable where `?? null` suppresses an undefined-key / uninitialized notice (load-bearing). |

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

Don't "fix" it by deleting the Option and widening to a raw union — that is
backwards. A plain `?Thing` is only right when you never wanted an Option here.
