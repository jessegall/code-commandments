---
name: spatie-data
description: How to write a Spatie Data class — final, public readonly promoted props; construct via `::from([...])` not `new` (except a `new` default value in the constructor); document the construction entry points at the top; never hand-hydrate field-by-field; typed collections via `#[DataCollectionOf]` + `::collect()`; honest field types (no all-nullable DTO); `Optional` vs `?T` vs default; class-level `#[MapInputName]`; `#[WithCast]`; validation. Read this FIRST whenever you write or review a `Data` class, a `::from`, a `new SomeData`, a hydrator, or a `#[...]` data attribute.
---

# Spatie Data — let the class build itself

> A `Data` class already knows how to be built from an array, copied, serialised, and collected. Your job
> is to declare **honest, typed, readonly** properties and let the framework do the mapping. The moment
> you hand-roll the array↔object plumbing, you've taken work the library does declaratively — and made it
> wrong.

## The house shape

```php
use Spatie\LaravelData\Data;

/**
 * Typed view of the inspect_trace tool's arguments.
 *
 * @method static static from(array{nodeId?: string, offset?: int, limit?: int} $data)
 */
final class InspectTraceInput extends Data
{

    public function __construct(
        public readonly string $nodeId,
        public readonly int $offset = T_Int::ZERO,
        public readonly int $limit = 5,
    ) {}
}
```

- **`final`**, **public `readonly`** promoted constructor properties. Immutable by construction — built
  once, never mutated. No setters; "change" a field by building a new one with `::from([...])`.
- **Construct via `::from([...])`, not `new`** — see the next section.
- **Document every `::from()` shape at the top with `@method`** — see ["Document every `::from()` shape"](#document-every-from-shape-with-method)
  below. `::from()` is magic and variadic, so the shapes it accepts are invisible at a glance.

## Construct with `::from()`, never `new` — except defaults

Build every data object through **`::from([...])`** (or a named `fromX()` factory), at every call site:

```php
$input = InspectTraceInput::from(['nodeId' => $id, 'limit' => 10]);   // good
$input = new InspectTraceInput($id, T_Int::ZERO, 10);                  // avoid — raw, positional, unmapped
```

`::from()` runs the framework pipeline — name mapping, casts, (request-sourced) validation, magic
factories. `new` bypasses all of it and binds you to positional args. Routing construction through
`::from()` keeps it uniform and mapped, and keeps the construction *shape* in the one documented place.

**The one place `new` is right** — a **default value in a constructor signature**, exactly as the docs
show. That is a default, not construction-from-input:

```php
public function __construct(
    public readonly ValueBag $meta = new ValueBag,   // good — a default value, not building from input
) {}
```

Never hand-roll the mapping: a static `fromArray()`/`fromRow()` reading keys one-by-one into
`new self(...)` re-implements what `::from()` already does. If a source genuinely needs conversion, write
an explicit magic factory — `public static function fromUser(User $user): self`.

## Document every `::from()` shape with `@method`

`::from()` **magically dispatches** to any `fromX(T $x)` factory whose parameter type matches the payload
(the resolver tests each method's `accepts()`), so `ProfileData::from($user)` silently calls `fromUser`.
The IDE and the reader cannot see that from the constructor. So:

**Every `fromX(T)` factory gets a matching `@method static static from(T $x)` line in the class docblock —
plus one `@method` for the base array shape.** That is the single place the full construction surface is
visible.

```php
/**
 * @method static static from(array{id: string, name: string} $data)
 * @method static static from(User $user)
 */
final class ProfileData extends Data
{

    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}

    public static function fromUser(User $user): self
    {
        return self::from(['id' => $user->id, 'name' => $user->name]);
    }
}
```

> **`FromArrayOnly` is optional.** The codebase currently mixes in a `FromArrayOnly` trait that makes
> `::from()` array-only (no model/request magic) and adds `::make()` / `::forArray()`. That is a separate,
> orthogonal choice from the use-`::from`-not-`new` rule above — keep it if you want predictable array-only
> construction, drop it if you'd rather use Spatie's native `::from()` polymorphism. Either way, the
> construction-style rule is the same.

## Honest field types — this is where most damage happens

A `Data` class's constructor signature **is** a contract. Type each field to the truth:

- A **required** field is **non-nullable, no default** — then `::from([...])` throws on a missing value,
  at the boundary, loudly. That is the point.
- An **optional** field uses `Optional`, `?T`, or a default — a *deliberate* three-way choice. **Do not
  reach for `?T = null` reflexively to dodge a missing key.** Which one is the [`absence`](../absence/SKILL.md)
  decision; the Spatie mechanics are:

| You want… | Use | In `toArray()` |
|---|---|---|
| key may be **absent**, omit it when missing (PATCH / partial) | `string \| Optional $x` | excluded entirely |
| value may be **explicit null**, key always present | `?string $x` / `string \| null $x` | `null` |
| a concrete **fallback** when absent | `string $x = 'default'` | the default |

**The anti-pattern (see `fix-at-the-source`):** a DTO where *every* field is `?T = null`. It validates
nothing and pushes every required-field check downstream into the consumers. If you're tempted to make a
field nullable so a consumer's check passes — stop, that's a symptom; the field's nullability is decided
by *this* class's real contract, not by what quiets a caller.

## Collections — type the element, collect the list

```php
// Bad — hydrating element-by-element
$songs = [];
foreach ($rows as $row) { $songs[] = SongData::from($row); }

// Good — declare the element type, let the framework collect
#[DataCollectionOf(SongData::class)]
public readonly array $songs;
// ...
$songs = SongData::collect($rows);   // array in -> array of SongData
```

- **`#[DataCollectionOf(X::class)]`** (or a `/** @var X[] */` docblock) on the property is **required** —
  element typing drives both hydration and nested validation (`songs.*.title`). A `::from()` inside a
  `foreach`/`array_map` is the tell you forgot it.
- **`::collect()` is magic, like `::from()`** — and **shape-preserving**: array → array, `Collection` →
  `Collection`, a paginator stays a paginator (with its `meta`). Force a target with
  `SongData::collect($rows, DataCollection::class)`.
- A source needing conversion gets a **`collectX()`** method (mirrors `fromX()`); document it the same way.

## Mapping, casts, validation — the bits you actually use

- **Name mapping at the class level:** for a snake_case boundary (LLM / external JSON), one
  `#[MapInputName(SnakeCaseMapper::class)]` on the class — never hand-write `#[MapInputName]` on every
  property.
- **`#[WithCast]` for non-scalar input** the framework can't auto-build (a `DateTime`, an enum from a
  non-trivial value). Don't write a cast for a **nested Data class** (nesting is automatic) or a plain
  scalar. **Gotcha:** a property carrying `#[WithCast]` (or any value-injecting attribute) **cannot be
  `readonly`** — the framework injects into it after construction. Drop `readonly` on exactly that
  property, nowhere else.
- **Validation:** prefer **declarative attributes** (`#[Required]`, `#[Min(1)]`, `#[Email]`) for static,
  per-property constraints. Use a static `rules()` only for **conditional / cross-field** logic — and
  return an **array** (`['field' => ['required', 'email']]`), never a pipe-string (`'required|email'`).
  Remember plain `from($array)` does **not** validate; validation runs on request-sourced creation.
- **`#[TypeScript]`** on any shape the frontend consumes, so the TS type stays in sync.

## Don't reach for (not our style)

`Lazy` properties, output `#[WithTransformer]`s, `validateAndCreate()`, `#[WithoutValidation]`,
hand-written `fromX()` magic methods that just re-do default array mapping. The codebase doesn't use them;
if you think you need one, you probably want a typed accessor / method on the class instead.

## Checklist

```
Spatie Data
- [ ] Built via ::from([...]) (or a fromX() factory), never `new` — except a `new` default value in the signature.
- [ ] Construction entry points documented at the top (class docblock / @method static self from(array{...})).
- [ ] final class; public readonly promoted props (except a #[WithCast] property, which can't be readonly).
- [ ] Required fields are non-nullable/no-default (from() throws on miss); optional is a DELIBERATE Optional/?T/default.
- [ ] NOT an all-nullable DTO — the type tells the truth about what's required.
- [ ] Collections: #[DataCollectionOf] + ::collect(), never ::from() in a loop.
- [ ] snake_case boundary -> one class-level #[MapInputName(SnakeCaseMapper::class)]; #[TypeScript] on FE-shared shapes.
```

## Relationship to the other skills

- [`fix-at-the-source`](../fix-at-the-source/SKILL.md) — a Data class IS a boundary; type it total so consumers
  don't re-validate. The all-nullable DTO is the canonical symptom-deferral.
- [`absence`](../absence/SKILL.md) — owns the `Optional` vs `?T` vs default decision; this skill only gives the
  Spatie mechanics for each.
- [`exceptions`](../exceptions/SKILL.md) — a required field missing at `::from()` should fail hard; surface it
  named when you catch the framework's exception at a tolerant boundary.
