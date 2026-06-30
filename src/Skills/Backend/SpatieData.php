<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

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

    public function description(): string
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

### Collections — type the element, collect the list

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
