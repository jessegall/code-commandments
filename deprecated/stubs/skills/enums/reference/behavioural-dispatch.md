# Behaviour on the case

Once a value is an enum, logic keyed off its cases belongs **on the type** — not
re-derived at every call site with an inline `match`. Three shapes, from lightest
to heaviest:

1. a value/label `match` → a **method on the enum**;
2. a wide behavioural `match` whose arms call collaborators → **one strategy
   object per case + a registration map**;
3. a raw `===`/`!==` comparison → the **null-safe `CompareSelf` trait**.

---

## 1. Value / label dispatch belongs on the enum

A `match`/`switch` whose subject is an enum and whose arms map ≥ 2 cases to
per-case results is behaviour that lives outside the type — and usually
duplicated across call sites.

```php
// BAD — the dispatch lives in the caller (and is copied to other callers)
match ($operator) {
    CompareOperator::GreaterThan => (float) $a > (float) $b,
    CompareOperator::Contains    => str_contains($a, $b),
    CompareOperator::Equals      => $a === $b,
    // …
};
```

```php
// GOOD — name it on the enum and call it
enum CompareOperator
{
    public function evaluate(mixed $a, mixed $b): bool
    {
        return match ($this) {
            self::GreaterThan => (float) $a > (float) $b,
            self::Contains    => str_contains($a, $b),
            self::Equals      => $a === $b,
            // …
        };
    }
}

$operator->evaluate($a, $b);
```

The degenerate single-case form — a ternary mapping one **non-enum type
constant** to a substitute and passing the rest through — is the same smell:

```php
// BAD
$port->type === WireType::MIXED ? 'any' : $port->type
```

```php
// GOOD — name the mapping on the value type
WireType::label($port->type)   // 'any' for MIXED, else the type
```

### When to leave it

- The arms call the enclosing object's own methods (`Effect::Retype => $this->retypeInput()`).
  Pushing that onto the enum would invert the layering (enum → caller). That is
  **strategy dispatch** — see section 2.
- Cross-type translation: every arm produces a case/const of a **different** type
  (`RunStatus::Ok => LogLevel::Info`). A method on the matched enum would couple
  it to that other type — the map is a translation, not the enum's own behaviour.
  (Scalar labels — `St::A => 'a'` — are NOT exempt: `label()`/`weight()` belong
  on the enum.)
- A `match (true)` / guard, or arms over mixed / non-enum subjects.
- The dispatch lives **inside the enum's own file** — that is the destination.

---

## 2. Wide behavioural dispatch → strategy objects

When a wide `match` (≥ 5 arms, configurable) dispatches per enum case and each
arm is **behaviour** — it calls methods, branches inline, `new`s something,
reaches for collaborators — it is a strategy table written inline. Each new case
widens the match (often a sibling one too), and no case is testable on its own.

```php
// BAD — a wide behavioural match (with a SECOND wide match in default)
private function applyEffect(NodeDescriptor $d, SocketEffect $rule, PickedValue $p): NodeDescriptor
{
    return match ($rule->effect) {
        PickEffect::ResourceToken => $d->retypeInput($rule->port, WireType::resource($p->raw)->toToken()),
        PickEffect::SchemaToken   => $this->objects->fieldsFor($p->raw)->isNone() ? $d : $d->retypeOutput(...),
        // … 4 more …
        default => $this->applyModelEffect($d, $rule, $p->modelClass),   // a SECOND wide match
    };
}
```

```php
// GOOD — one applicator per case behind an interface…
interface SocketEffectApplicator
{
    public function apply(NodeDescriptor $d, SocketEffect $rule, PickedValue $p): NodeDescriptor;
}
final class ResourceTokenEffect implements SocketEffectApplicator { /* one effect's rewrite */ }
// … one class per case …

// …with the registration homed in a DEDICATED injected provider:
final class SocketEffectApplicators
{
    /** @var array<string, SocketEffectApplicator> */
    private readonly array $applicators;

    public function __construct(SchemaTypeRegistry $objects)
    {
        $this->applicators = [
            PickEffect::ResourceToken->value => new ResourceTokenEffect,
            PickEffect::SchemaToken->value   => new SchemaTokenEffect($objects),
            // …
        ];
    }

    public function for(PickEffect $e): SocketEffectApplicator
    {
        return $this->applicators[$e->value];
    }
}

// original class: inject the provider, delegate — owns no map, no strategy deps
private function applyEffect(NodeDescriptor $d, SocketEffect $rule, PickedValue $p): NodeDescriptor
{
    return $this->effectApplicators->for($rule->effect)->apply($d, $rule, $p);
}
```

Adding a case becomes "a new class + one map entry" — open for extension, closed
for modification, each case testable alone.

### Naming

The provider is a TOTAL keyed lookup (`for($key): Strategy`, return-or-throw over
the closed keyspace). Name it for that: `XApplicators` / `XStrategies` / a neutral
`XMap`. **Not** `*Resolver` (that is first-match kernel dispatch — see the
`resolvers` skill) and **not** `*Factory` (overstates if it hands back shared
stateless strategies). Do NOT leave an inline map/builder method on the original
class — that just reshapes the wide table in place, keeping every strategy's deps
on the caller.

### When to leave it

- The arms are bare **constants / values** (`Case => 1`, `Case => OtherEnum::X`)
  — that belongs ON the enum as a method (section 1), not a strategy.
- A **small** match (< 5 arms) — inline reads better than N files.
- The dispatch is already a method call on the type, or the arms share no common
  shape, so a single `apply(...)` interface would not fit.

---

## 3. Null-safe comparison via CompareSelf

Raw enum equality with `===` / `!==` scatters comparison logic and is **not
null-safe** — a null or non-enum left-hand side silently fails the test rather
than answering it. The scaffolded `{{ namespace }}\CompareSelf` trait names the
comparison and makes it null-safe under one family of helpers. Add `use CompareSelf;`
to the enum, then:

| Raw form | Rewrite |
|---|---|
| `$x === Status::A` | `Status::A->equals($x)` |
| `$x !== Status::A` | `Status::A->notEquals($x)` |
| `$x === Status::A \|\| $x === Status::B` | `Status::equalsAny($x, Status::A, Status::B)` |
| `$x !== Status::A && $x !== Status::B` | `Status::notEqualsAny($x, Status::A, Status::B)` |

A **single** comparison anchors on the case (`Status::A->equals($x)`) — the case
literal is never null, so it is just as null-safe and reads better. The **static**
`equalsAny` / `notEqualsAny` form is for multi-case sets only, where there is no
single case to anchor on. Using the static singular form against a known case is
the wrong shape and is flagged as a sin:

```php
// BAD — static singular against a literal case
Status::equals($x, Status::A);
```
```php
// GOOD — anchor on the case
Status::A->equals($x);
```

### When to leave it

- The other operand is the enum's **backing scalar** (a string/int/`mixed`), not
  an enum instance — `equals(?Enum)` would `TypeError` on it. Leave the `===`.
- A load-bearing **narrowing guard**: a `=== Enum::Case` that is the sole
  condition of an `if` that bails (`continue`/`return`/`throw`/`break`). PHPStan
  narrows through `===` but NOT through `equals()`; converting it would break a
  later exhaustive `match`. Left alone.
- Wire-format boundaries (`toArray`, `jsonSerialize`, `render`, a
  `JsonResource`/`Resource`/`Response` class). Left alone.
- The enum has **not adopted the trait yet** — add `use CompareSelf;` first, then
  the rewrite applies (the prophet emits a one-time adoption nudge, not an
  auto-fix, until the trait is in place).

---

## Enforced by

`PreferTypeMethodOverInlineDispatch` (value/label dispatch on the enum),
`BehaviouralEnumDispatch` (wide behavioural dispatch → strategy objects), and
`SuggestCompareSelfTrait` (raw `===`/`!==` → the null-safe `equals` family;
auto-fixable once the trait is adopted).

```
commandments:scripture --prophet=PreferTypeMethodOverInlineDispatch
commandments:scripture --prophet=BehaviouralEnumDispatch
commandments:scripture --prophet=SuggestCompareSelfTrait
```
