# Immutable data — patterns

A Spatie `Data` class is the framework's array↔object boundary. The five
patterns below cover everything the prophet family enforces. The shared rule:
**let `Data` do the mapping; you only declare the shape.**

The trait the examples `use` is the scaffolded one at
`{{ namespace }}\FromArrayOnly` (run `commandments:scaffold` if it is missing).

---

## 1. Extend Data + use FromArrayOnly — never hand-roll hydration

A static `fromArray()` that reads keys one by one and feeds `new self(...)` is
dead code the moment the class extends `Data` — `::from()` does ALL of it
(automatic key→parameter mapping, type coercion from the declared types,
recursive hydration of nested Data, defaults in place of every `??`).

Bad — hand-rolled hydrator reimplementing the framework:

```php
final readonly class FieldSpec
{
    public function __construct(
        public string $name,
        public string|null $label,
    ) {}

    public static function fromArray(array $row): self|null
    {
        $name = Arr::get($row, 'name');
        if (! is_string($name)) {
            return null;
        }
        $label = Arr::get($row, 'label');

        return new self(
            name: $name,
            label: is_string($label) ? $label : null,
        );
    }
}
```

Good — extend `Data`, `use FromArrayOnly`, declare the shape; the types ARE the
validation and `= null` IS the fallback:

```php
use {{ namespace }}\FromArrayOnly;
use Spatie\LaravelData\Data;

final class FieldSpec extends Data
{
    use FromArrayOnly;

    public function __construct(
        public readonly string $name,
        public readonly string|null $label = null,
    ) {}
}

$spec  = FieldSpec::forArray($row);    // one row — explicit array entry, not magic ::from()
$specs = FieldSpec::collect($rows);    // a list of rows
```

The `FromArrayOnly` trait guards `::from()` to arrays (it asserts in dev/test,
compiles out in production) and gives you the explicit `forArray()` /  `make()`
entries. Put the trait on a **shared base** Data class and every subclass
inherits it — only the class that extends `Data` directly is flagged, and
`commandments repent` can add the `use` + import for you.

- snake_case input? Put `#[MapInputName(SnakeCaseMapper::class)]` on the class —
  don't rename keys by hand.
- Custom coercion for ONE field? Add a `Cast` — never a hand-written
  constructor wrapper, and never a `from`-prefixed method (the `from` prefix is
  reserved for Spatie's magic `::from()` and can recurse → segfault).
- Tolerant decoding of untrusted input (LLM output, webhooks)? Make every
  property nullable with a default, hydrate, THEN validate the typed object; wrap
  `::from()` in `try/catch` when rejection is expected.

---

## 2. Object→object mapping lives ON the target, not at the call site

Building a foreign class field-by-field from one source object is the mapping
equivalent of `fromArray()`.

Bad — mapping spelled out at the call site:

```php
$fieldOutputs = array_map(
    static fn (OutputPort $port) => new OutputPort(
        name: 'value.' . $port->name,
        type: $port->type,
        nullable: $port->nullable,
        label: $port->label ?? $port->name,
        description: $port->description,
    ),
    $ports,
);
```

Good — the mapping is a NAMED factory on the target (`for*`, never `from*`):

```php
// In OutputPort:
public static function passThrough(self $port, string $prefix = 'value.'): self
{
    return new self(
        name: $prefix . $port->name,
        type: $port->type,
        nullable: $port->nullable,
        label: $port->label ?? $port->name,
        description: $port->description,
    );
}

$fieldOutputs = array_map(OutputPort::passThrough(...), $ports);
```

---

## 3. Copy-with-changes is a missing wither

When source and target are the SAME type, re-listing every constructor field to
change one is a missing `copyWith` (named `copyWith`, NOT `with` — Spatie `Data`
already defines `with()` for transformation payload).

Bad:

```php
return new NodeDescriptor(
    key: $descriptor->key,
    kind: $descriptor->kind,
    label: $descriptor->label,
    // ...nine more copied fields...
    traceHandles: [...$descriptor->traceHandles, 'next'],
);
```

Good:

```php
public function copyWith(mixed ...$changes): static
{
    $fields = get_object_vars($this);

    return new static(...[...$fields, ...$changes]);
}

return $descriptor->copyWith(traceHandles: [...$descriptor->traceHandles, 'next']);
```

The exemption IS the rule: factories and withers themselves construct from
another instance's (or `$this`'s) properties — inside the target class that is
righteous, because the mapping finally has one home.

---

## 4. `readonly` and value-injecting attributes don't mix in the class body

Laravel Data injects property values through attributes (`#[WithCast]` and other
`InjectsPropertyValue` implementations). That injection cannot write to a class-
body `readonly` property.

Bad — `#[WithCast]` on a `readonly` class-body property:

```php
class UserData extends Data
{
    #[WithCast(DateTimeCast::class)]
    public readonly Carbon $createdAt;     // injection can't write here
}
```

Good — drop `readonly` when the property is injected:

```php
class UserData extends Data
{
    #[WithCast(DateTimeCast::class)]
    public Carbon $createdAt;
}
```

Also good — `readonly` is fine without an injecting attribute, or when the cast
sits on a **constructor-promoted** parameter:

```php
public readonly string $name;          // no injecting attribute → readonly is OK

public function __construct(
    #[WithCast(DateTimeCast::class)]
    public readonly Carbon $createdAt,  // promoted param → readonly is OK
) {}
```

So: prefer `readonly` everywhere, and the moment you add `#[WithCast]` / an
injecting attribute to a **class-body** property, drop the `readonly` (or move
the property into the constructor).

---

## 5. Serialise through `->toArray()` + transformers, not a hand map

A `Data` object already serialises itself — `->toArray()` runs its name mapping,
casts, and transformers. Re-deriving that by hand in a separate method
duplicates the class's job and drifts from it.

Bad — a bespoke serialiser maps the Data object field-by-field:

```php
private function serialiseInput(InputSocket $port): array
{
    return [
        'name'     => $port->name,
        'type'     => WireType::label($port->type),   // custom shaping
        'required' => $port->required,
        'nullable' => $port->nullable,
        'options'  => $port->options,
    ];
}
```

Good — let the object transform itself; put per-field shaping ON the class:

```php
class InputSocket extends Data
{
    public function __construct(
        public string $name,
        #[WithTransformer(WireTypeLabelTransformer::class)]
        public string $type,
        // …
    ) {}
}

$port->toArray();
```

Per-field rules live in `#[WithTransformer]` (one property), a global transformer
in `config/data.php` (a whole type), or a cast. This is a design move (where each
rule lives), so it is NOT auto-fixed.

---

## 6. Hydrate a collection declaratively, not with `::from()` in a loop

A `foreach` that appends `SomeData::from($row)`, or an `array_map` whose callback
is `SomeData::from`, is one element of boilerplate at a time.

Bad — manual loop / manual array_map:

```php
$hydrated = [];
foreach ($outcome->steps as $row) {
    $hydrated[] = StepEntry::from($row);
}
return $hydrated;

return array_map(static fn (array $entry) => Field::from($entry), $entries);
```

Good — let the framework hydrate the whole collection:

```php
// On the property:
#[DataCollectionOf(StepEntry::class)]
public readonly DataCollection $steps;

// Or, ad hoc:
return StepEntry::collect($outcome->steps);
return Field::collect($entries);
```

Only a straight `::from` is flagged. A custom mapper —
`array_map(fn ($p) => NodePortData::forInputPort($p), $ports)` — is genuine
per-element transformation, not collection hydration, and is left alone.

---

## Decision table

| You are writing… | Do this |
|---|---|
| A class that maps an array to a typed object | Extend `Data`, `use FromArrayOnly`, declare typed (`readonly`) props; hydrate via `::forArray()` / `::collect()` |
| A static `fromArray()`/`fromRow()` reading keys by hand | Delete it — `::from()` already does the mapping/coercion/nesting |
| `new Foo(...)` from another object's fields at a call site | Move it to a named `for*` factory ON `Foo` |
| `new Foo(...)` re-listing every field to change one | Add a `copyWith(...)` wither (named `copyWith`, not `with`) |
| A `readonly` prop that needs `#[WithCast]` / injection | Drop `readonly` (class body) or move it to a promoted constructor param |
| A method assembling a Data object's props into an array | `->toArray()` + `#[WithTransformer]` / casts on the class |
| `SomeData::from($x)` inside a `foreach`/`array_map` | `#[DataCollectionOf(SomeData::class)]` or `SomeData::collect($source)` |

## When to reach for it

- The value crosses the array boundary (request, DB row, decoded JSON, queue
  payload) and has a fixed, typed shape — make it a `Data` class with
  `FromArrayOnly`.
- You're about to type `Arr::get` more than once in a static creator, or forward
  three properties of the same object into a constructor — the mapping belongs on
  the target class.
- A Data object needs to become an array, or a list of them needs hydrating —
  use `->toArray()` / `::collect()`.

## When to leave it

- **Not array-constructible.** A `Fluent` bag, a plain collection, or a
  `__construct(array $x)` value object is not a Data class — use a
  `::coalesce()` factory (see the `coalesce-factories` skill), not `::from()`.
- **A genuinely different output shape.** A serialiser that drops/renames/derives
  *most* fields for one specific wire contract is a projection, not a
  reimplementation of `->toArray()` — a transformer on the Data class would be
  the wrong home. Leave it.
- **A real per-element transformation.** A loop that filters, branches,
  accumulates, or maps each element through a custom factory is not collection
  hydration — `#[DataCollectionOf]` does not apply.
- **Magic-dependent hierarchies.** A class (or a subclass that would inherit the
  trait) that depends on Spatie's magic `from(Model)` —  carrying
  `#[LoadRelation]` / `#[MapInputName]` / `#[MapName]` / `#[Computed]`, or an
  abstract / `PropertyMorphableData` base — must NOT get `FromArrayOnly`; the
  array-only assert would fatal. The two prophets stay coupled: a class gets
  EITHER (trait + every object `::from()` converted) OR neither.
- **Still mid-migration.** If `::from(object)` call sites remain, fix those first
  (set the prophet to `warning` while you do) so the trait's assert doesn't fire
  in dev/test.

---

Enforced by `ReadonlyDataProperties`, `NoManualHydration`,
`DataClassFromArrayOnly`, `PreferDataTransformers`, `PreferDataCollectionOf`.
Read the full rule for any one with
`commandments:scripture --prophet=<NAME>` (e.g. `--prophet=NoManualHydration`).
