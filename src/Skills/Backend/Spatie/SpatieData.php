<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend\Spatie;

use JesseGall\CodeCommandments\Skills\Backend\Absence;
use JesseGall\CodeCommandments\Skills\Backend\Exceptions;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class SpatieData extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/spatie-data',
            tier: Tier::Mandatory,
            order: 4,
        );
    }

    public function title(): string
    {
        return "Spatie Data — let the class build itself";
    }

    public function trigger(): string
    {
        return "How to write a Spatie Data class — final, public readonly promoted props; construct via `::from([...])` not `new` (except a `new` default value in the constructor); magic factories MUST be named `from<Type>` (the prefix is required to dispatch); document the magic `from`/`collect` overloads with `@method` naming `from`/`collect` — never the factory's own name (that re-declares a real method) nor the array shape; never hand-hydrate field-by-field; typed collections via `#[DataCollectionOf]` + `::collect()`; honest field types (no all-nullable DTO); `Optional` vs `?T` vs default; class-level `#[MapInputName]`; `#[WithCast]`; validation. Read this FIRST whenever you write or review a `Data` class, a `::from`, a `new SomeData`, a hydrator, or a `#[...]` data attribute.";
    }

    public function intro(): string
    {
        return "A `Data` class already knows how to be built from an array, copied, serialised, and collected. Your job
is to declare **honest, typed, readonly** properties and let the framework do the mapping. The moment
you hand-roll the array↔object plumbing, you've taken work the library does declaratively — and made it
wrong.";
    }

    public function summary(): string
    {
        return "how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A `Data` class already knows how to be built from an array, copied, serialised, and collected. Your job
is to declare **honest, typed, readonly** properties and let the framework do the mapping. The moment
you hand-roll the array↔object plumbing, you've taken work the library does declaratively — and made it
wrong.

### The house shape

- **`final`**, **public `readonly`** promoted constructor properties. Immutable by construction — built
  once, never mutated. No setters; "change" a field by building a new one with `::from([...])`.
  - **A `#[WithCast]` on a promoted param is still `readonly`.** The cast runs INSIDE the `::from()`
    pipeline, BEFORE the object exists — it morphs the raw value and Spatie passes the *built* result as
    the constructor argument. So the value is present at construction; `readonly` holds. Don't demote a
    cast property to mutable "because the cast injects it after construction" — that only happens for a
    **non-promoted** class property (`public T $x;` outside the constructor), which Spatie sets by
    reflection post-construction and which therefore can't be `readonly`. The fix for that is to *promote*
    it into the constructor, not to drop `readonly`.
- **Construct via `::from([...])`, not `new`** — see the next section.
- **Document non-array `fromX()` factories with `@method`** (see below) — *only* when you add a
  `fromX(SomeClass $x)` magic factory. The plain `::from([...])` array shape needs **no** `@method`: it's
  the default, already visible in the constructor. A class with no `fromX()` factories needs no `@method`.

### Construct with `::from()`, never `new` — except defaults

Build every data object through **`::from([...])`** (or a named `fromX()` factory), at every call site.

`::from()` runs the framework pipeline — name mapping, casts, (request-sourced) validation, magic
factories. `new` bypasses all of it and binds you to positional args. Routing construction through
`::from()` keeps it uniform and mapped, and keeps the construction *shape* in the one documented place.

**`new` is fine on a PLAIN data class.** The rule is about not *silently bypassing a pipeline*. If the
class has no pipeline to bypass — only scalar/enum promoted props, no `#[WithCast]`/`#[WithCastable]`,
no `#[MapInputName]`/`#[MapName]`, no `#[DataCollectionOf]`, no nested `Data` prop, no `casts()`, no
`fromX()` factory — then `::from()` and `new` do exactly the same thing, and `new` (with named args) is
honest and clearer.

The sin is `new` on a **rich** class — one that *does* carry casts, name maps, nested Data, or a magic
factory — where the raw `new` quietly skips that work.

**The other place `new` is always right** — a **default value in a constructor signature**, even on a rich
class. That is a default, not construction-from-input.

Never hand-roll the mapping: a static `fromArray()`/`fromRow()` reading keys one-by-one into
`new self(...)` re-implements what `::from()` already does. If a source genuinely needs conversion, write
an explicit magic factory — `public static function fromUser(User $user): self`.

### Document the magic `::from()`/`::collect()` overloads with `@method` — never the factory or the array shape

`::from()` **magically dispatches** to a custom factory whose parameter type matches the payload, so
`ProfileData::from($user)` silently runs `fromUser($user)`. The IDE and the reader cannot see that hidden
overload from the constructor — that, and only that, is what `@method` is for.

The dispatcher's mechanics, which must be exactly right or the magic doesn't exist:

- **A magic factory's name MUST begin with `from`** (and cannot be exactly `from`). The dispatcher only
  considers public-static `from*` methods. A factory named `forCredential`, `ofUser`, `createFromRow`,
  `make…` is **invisible to `::from()`** — callers must invoke it directly and it gets no polymorphism.
  If you want `::from($x)` to route to it, **rename it `from<Type>`** (`forCredential(Credential $c)` →
  `fromCredential`). Multiple args are fine: `fromMultiple(string $t, string $a)` answers
  `ProfileData::from($t, $a)`.
- **The `@method` line documents `from` — NEVER the factory's own name.** Writing
  `@method static static fromCredential(...)` (or `forCredential(...)`) re-declares a method the class
  *already* has, so the IDE reports **"Method with same name already defined in this class."** The
  concrete `from<Type>` is visible from its real signature; the *invisible* thing — the one the annotation
  must describe — is the `::from(<that type>)` overload. So the tag always names `from`.

Each `from<Type>(T)` *object* factory gets one matching `@method static static from(T $x)` line.

**Do NOT `@method`-document the array shape.** `::from([...])` from an array is the default; the
constructor already shows the keys it takes, so a verbose `@method static static from(array{a?: …, b?: …})`
just duplicates it as noise. Only the *non-array* factory types earn a `@method`.

### Honest field types — this is where most damage happens

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

**When every field genuinely IS optional and same-shaped**, with no required core field to demand, the
honest shape is *not* `?T = null` on each, and *not* flattening the bag into its parent (that loses the
cohesive shape). If the field's type has a natural **inert / identity value**, keep each field
**non-nullable with a [Null Object](../absence/SKILL.md) default** on the value type. Now consumers never
null-check, the wire contract carries no nullables, and an "undeclared" field is an *inert value* rather
than an absence. This is the `absence` skill's Null Object option applied to a whole DTO — reach for it
before you accept an all-nullable bag.

This is **not** a callback-only move — it fits any type with an identity value:

- a **callback/hook bag** — `AcceptCallbacks { startDrag, received, moved, removed: Callback }` → each
  defaults to `new Callback(Callback::NOOP)`, with `noOp()`/`isNoOp()` on `Callback`. The unset hook is
  a verb nothing listens to.
- a **filter/criteria set** — `SearchFilters { status, assignee, dueBefore }` → each defaults to a
  "match-all" object (`StatusFilter::any()`, `DateFilter::unbounded()`), so the query builder applies
  every filter unconditionally, no `if ($x !== null)` branch.
- a **money/quantity bag** — `PriceBreakdown { discount, tax, shipping: Money }` → each defaults to
  `Money::zero()`, so `subtotal + tax + shipping` needs no null coalescing.

Where the type has **no** honest identity value, the field is not really always-optional — it has a real
contract; go back to the required/`Optional`/`?T` choice above.

### `Optional` — for a value that may be genuinely ABSENT

`Optional` (`Spatie\LaravelData\Optional`) is **not** another word for `null`. It marks a field that may be
**missing from the payload entirely**, and Spatie treats it structurally:

- **Hydration** — `::from([...])` with the key absent leaves the field an `Optional` instance; you never
  pass a value. Constructing by hand, give it that default so partial construction is legal:
  `public readonly string|Optional $artist = new Optional()` (equivalently `Optional::create()`).
- **Output** — an `Optional` field is **omitted from `toArray()`/JSON entirely**, not emitted as `null`.
  That is the whole point: `{"title": …}` with **no** `artist` key — versus `?string`, which emits
  `"artist": null`. So `Optional` is the honest, leaner way to say "this simply isn't here", and it is the
  clean way to prune a `?T = null` field from the wire: retype it `T|Optional = new Optional()`.
- **Need both "absent" and "explicit null"?** `string|Optional|null`, then
  `SomeData::factory()->withoutOptionalValues()->from([...])` collapses `Optional` → `null` for output.

Read the three-way as a statement about the **wire**: `Optional` = the key vanishes; `?T` = key present,
`null`; `T = default` = key present, the fallback. Choose by what the consumer of the JSON must see.

### Put the optionality at its SOURCE — the coarsest honest boundary

This is the cardinal rule (**trace to the source**) applied to absence. When a whole **sub-object** may be
absent, the optional belongs on the **container field where the object is used** — NOT scattered across
that object's own leaves:

```php
// ✗ WRONG — every leaf carries Optional, so a Grid can exist half-built (no columns, no span). The
//   absence is smeared across the children and the object's own invariant ("a grid has columns") is gone.
final class Grid extends Data {
    public function __construct(
        public readonly int|Optional $columns = new Optional(),
        public readonly int|Optional $span    = new Optional(),
    ) {}
}

// ✓ RIGHT — Grid is ALWAYS whole, with honest concrete defaults; "is there a grid at all?" lives on the
//   PARENT's field. The invariant holds: if a Grid exists, it is a valid grid.
final class Grid extends Data {
    public function __construct(
        public readonly int $columns = 1,
        public readonly int $span    = 1,
    ) {}
}
final class UiChrome extends Data {
    public function __construct(
        public readonly Grid|Optional       $grid  = new Optional(),
        public readonly StyleToken|Optional $style = new Optional(),
    ) {}
}
```

**A `Data` class where EVERY field is `T|Optional` is the same god-DTO smell as all-`?T = null`** — the
type now promises that *nothing* is ever present. Don't trade one lie for the other. Ask where the absence
is really born: almost always it is the *whole object* that is present-or-absent, so make the enclosing
field `T|Optional` and give this object honest, concrete leaves.

### Collections — type the element, collect the list

- **NEVER type a property as `DataCollection`.** The property type is **`array`** (preferred) or a
  `Collection`, with **`#[DataCollectionOf(X::class)]`** naming the element — `public readonly array $nodes;`
  with the attribute, never `public readonly DataCollection $nodes;`. `DataCollection` is a fine *return*
  of `::collect()`, but as a property TYPE it generates malformed TypeScript (`undefined<number, X>`) and
  skips the element-typed hydration/validation below. (`#[LiteralTypeScriptType('X[]')]` is NOT the fix —
  `array` + `#[DataCollectionOf]` is.)
- **`#[DataCollectionOf(X::class)]`** (or a `/** @var X[] */` docblock) on the property is **required** —
  element typing drives both hydration and nested validation (`songs.*.title`). A `::from()` inside a
  `foreach`/`array_map` is the tell you forgot it.
- **`::collect()` is magic, like `::from()`** — and **shape-preserving**: whatever collection type you pass
  in comes back out, holding the data objects. `array` → `array`, `Illuminate\Support\Collection` →
  `Collection`, an Eloquent collection → Eloquent collection, a `LengthAwarePaginator`/`CursorPaginator`
  stays that paginator. The optional second arg casts a **non-paginator** collection into a target type:
  `SongData::collect($rows, DataCollection::class)`.
- **Document `collect()` with a *conditional* `@method`, never a flat `static[]`.** A flat array return
  lies the moment a caller passes a `Collection`. Encode the shape-preservation so the IDE/PHPStan infers
  the real return from the argument. PhpStorm renders the conditional as the union
  (`Collection<int, static>|array<int, static>`), still honest; PHPStan/Psalm resolve the exact branch.
  Only annotate `collect()` when the class is actually `::collect()`-ed.
- A source needing conversion gets a **`collectX()`** method (mirrors `fromX()`); document it the same way.

### Mapping, casts, validation — the bits you actually use

- **Name mapping at the class level:** for a snake_case boundary (LLM / external JSON), one
  `#[MapInputName(SnakeCaseMapper::class)]` on the class — never hand-write `#[MapInputName]` on every
  property.
- **`#[WithCast]` for non-scalar input** the framework can't auto-build (a `DateTime`, an enum from a
  non-trivial value). Don't write a cast for a **nested Data class** (nesting is automatic) or a plain
  scalar. **Gotcha:** a property carrying `#[WithCast]` (or any value-injecting attribute) **cannot be
  `readonly`** — the framework injects into it after construction. Drop `readonly` on exactly that
  property, nowhere else.
- **Never hand-hydrate a value object in a constructor/factory.** `$this->price = new Money($raw['amount'],
  $raw['currency'])` re-does imperatively the `simple → complex` mapping a **cast** owns. Type the property
  as the value object and give it a `#[WithCast(MoneyCast::class)]`, or let the value object implement
  `Castable` (a `castUsing()` returning the cast). A custom cast is a tiny class implementing `Cast` —
  `cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed` —
  returning the built object (or `Uncastable` when it can't; it never receives `null`). Hydration stays
  declarative and reusable, out of the constructor. (Still not for a nested `Data` — that nests automatically.)
- **Validation:** prefer **declarative attributes** (`#[Required]`, `#[Min(1)]`, `#[Email]`) for static,
  per-property constraints. Use a static `rules()` only for **conditional / cross-field** logic — and
  return an **array** (`['field' => ['required', 'email']]`), never a pipe-string (`'required|email'`).
  Remember plain `from($array)` does **not** validate; validation runs on request-sourced creation.
- **`#[TypeScript]`** on any shape the frontend consumes, so the TS type stays in sync.

### Don't reach for (not our style)

`Lazy` properties, output `#[WithTransformer]`s, `validateAndCreate()`, `#[WithoutValidation]`,
hand-written `fromX()` magic methods that just re-do default array mapping. The codebase doesn't use them;
if you think you need one, you probably want a typed accessor / method on the class instead.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            FixAtTheSource::class => "a Data class IS a boundary; type it total so consumers don't re-validate. The all-nullable DTO is the canonical symptom-deferral.",
            Absence::class => "owns the `Optional` vs `?T` vs default decision; this skill only gives the Spatie mechanics for each.",
            Exceptions::class => "a required field missing at `::from()` should fail hard; surface it named when you catch the framework's exception at a tolerant boundary.",
        ];
    }
}
