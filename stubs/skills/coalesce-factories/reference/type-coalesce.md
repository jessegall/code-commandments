# Typed scalar/array construction — `T_*::coalesce` / `coalesceFor` / `coerce`

php-types ships a typed, named helper for every scalar/array wrapper. They say
"this nullable value, or its type's empty/zero/false" (`coalesce`), "the array
at this dynamic key, or `[]`" (`coalesceFor`), and "this value if it is the
right type, else the default" (`coerce`) — each in one place. The raw `??` /
guard-ternary idioms hide them and let the rule drift across call sites.

The wrappers: `T_Array`, `T_String`, `T_Int`, `T_Float`, `T_Bool`.

---

## 1. `?? <empty literal>` → `T_*::coalesce()`

Bad — raw empty-literal coalesce on a nullable typed value:

```php
$steps = $run->steps ?? [];          // ?array property
$steps = $run->steps ?? T_Array::EMPTY;
$name  = $this->label ?? '';         // ?string
$limit = $config->limit ?? 0;        // ?int
```

Good — the typed helper:

```php
$steps = T_Array::coalesce($run->steps);
$name  = T_String::coalesce($this->label);
$limit = T_Int::coalesce($config->limit);
```

The empty literals: `[]` / `''` / `0` / `0.0` / `false`, or the matching
`T_Array::EMPTY` / `T_String::EMPTY` / `T_Int::ZERO` / `T_Float::ZERO` /
`T_Bool::FALSE` constant. Auto-fixable: `repent` rewrites the `??`.

**Reach for it** in value positions (assignment, argument, `count(...)`,
constructor arg) when the left side resolves to a **nullable of the matching
type** — a `?array`/`?string`/… parameter, `$this` property, or a resolvable
object property.

**Leave it** when the left side is `mixed`/untyped (the type, hence the right
helper, is unknown — coercing would change semantics), when the value is *not*
nullable (the `??` is already dead — a different smell), when the default is
non-empty, or when it is just an inline `foreach (... ?? [] as ...)` guard.

Enforced by **PreferTypeCoalesce**. `commandments:scripture --prophet=PreferTypeCoalesce`.

---

## 2. Double-coalesced dictionary lookup → `T_Array::coalesceFor()`

`T_Array::coalesce($arr[$key] ?? null)` double-coalesces: `coalesce` already
**is** the `?? []`, so the inner `?? null` is noise — and even bare
`T_Array::coalesce($arr[$key])` is just `$arr[$key] ?? []` the long way.

Bad:

```php
$targets = T_Array::coalesce($forward[$current] ?? null);
$out     = T_Array::coalesce($outgoing[$node->id] ?? null);
```

Good:

```php
$targets = T_Array::coalesceFor($forward, $current);
$out     = T_Array::coalesceFor($outgoing, $node->id);
```

`coalesceFor($array, $key, $default = [])` returns the array at `$key`, or the
default when the key is absent or null — one call, no `??`. Auto-fixable
(`repent` carries a non-trivial default through as the third argument).

**Reach for it** only for a **dynamic key** — a variable / property / expression
(`$forward[$current]`, `$outgoing[$node->id]`), a genuine dictionary lookup.

**Leave it** for a **literal key** (`$config['label']`): that is a record
wearing a dictionary's clothes — `NoArrayStringIndexing`'s territory (introduce
a DTO), not rewritten here.

Enforced by **PreferCoalesceFor**. `commandments:scripture --prophet=PreferCoalesceFor`.

---

## 3. Repeated cast-with-fallback ternary → `T_*::coerce()`

A typed accessor over an untyped source (config, request, an array bag) is good
— but only if the coercion lives in ONE place. When the same
`type-guard ? cast : default` ternary is copy-pasted across methods of a class,
the cast rule and its fallback drift.

Bad — the same coercion inline in every method:

```php
public function maxInputTokens(): int
{
    $v = $this->config->get('….max_input_tokens', 80_000);

    return is_numeric($v) ? (int) $v : 80_000;
}

public function maxBodyBytes(): int
{
    $v = $this->config->get('….max_body_bytes', 1_048_576);

    return is_numeric($v) ? (int) $v : 1_048_576;   // same shape again
}
```

Good — the validated php-types helper, every site routes through it:

```php
public function maxInputTokens(): int
{
    return T_Int::coerce($this->config->get('….max_input_tokens'), 80_000);
}

public function maxBodyBytes(): int
{
    return T_Int::coerce($this->config->get('….max_body_bytes'), 1_048_576);
}
```

`coerce` **guards then falls back**, never blind-casts: `T_Int::coerce("abc", $d)`
is `$d`, not `0` — that is the difference from `coalesce`, which casts. A
per-class `intOr()` helper just re-duplicates the body across every accessor
class (and trips `DuplicateCode`); `T_*::coerce()` ends that. A `null` fallback
maps to `coerceOrNull($x)`.

| Situation | Verdict |
|---|---|
| `is_numeric($x) ? (int) $x : $d` repeated 2+× in a class | `T_Int::coerce($x, $d)` (auto-fixable) |
| `is_numeric($x) ? (float) $x : $d` repeated | `T_Float::coerce($x, $d)` (auto-fixable) |
| `is_scalar($x) ? (string) $x : $d` repeated | `T_String::coerce($x, $d)` (auto-fixable) |
| `is_string`-guarded site | Advisory only — `coerce` accepts any scalar, broader than `is_string` |
| A single occurrence (no duplication) | Leave it inline |
| Plain `?? / ?:` fallback chain (no type guard) | Not this rule — that is `RepeatedFallback` / `PreferTypeCoalesce` |
| Coercion wrapped in more work (`max(0, (int) $x)`) or a computed fallback | Leave it — not a bare coerce |

**Reach for it** when the *same* guard+cast+fallback shape appears in two or
more methods of one class.

**Leave it** when the coercion appears only once, or each site genuinely differs
(different guard, cast, or shape) so a shared helper would not fit.

Enforced by **PreferCoercionHelper**. `commandments:scripture --prophet=PreferCoercionHelper`.
