---
name: commandments-backend-page-objects
description: How to write a PAGE OBJECT — the composed Spatie `Data` a controller returns for one page to render (it composes several nested Data slots and travels back in the response). Inject collaborators from the container with `#[FromContainer]` and ALWAYS `#[Hidden]` (an un-hidden service leaks into the generated TypeScript); never reach for `app()`/`resolve()`/a facade inside the object. Express each slot as a `#[Computed]` property hook (`public T $x { get => … }`) seeded from the injected request/services, not a fat constructor of `$this->x = …` assignments — except a `Lazy`/deferred slot or a local unwrapped once for reuse, which stay in the constructor. Shape a property's OUTPUT with a `#[WithTransformer]` / a custom `Transformer` when its serialized wire shape must differ from its PHP type (a value object rendered as a string, a domain type flattened for the frontend) — never a hand-rolled getter that reshapes an array. Read this BEFORE you write or review a `*Page`/view-model Data class, a controller that returns a Data payload, a `#[FromContainer]`/`#[Hidden]`/`#[Computed]`/`#[WithTransformer]` on a Data property, a page object's constructor, OR whenever a Data property's type needs a different serialized shape than its PHP type (a `Money`, a `Carbon`, an enum, any value object on the wire) and you're deciding how to transform it.
---

# Page Objects — the Data class builds the whole page

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A page object is a `Data` class that assembles one page's whole payload and hands it back to the
> frontend. Let it build ITSELF: seed it from an id, pull its collaborators from the container (hidden), and
> let each slot be a computed projection — not a constructor that imperatively fills field after field.

## The principle

A **page object** is the single composed `Data` a controller returns for one page to render. It is not a
leaf DTO: it *composes several nested Data slots* (a canvas, a list of rows, a set of menus) and it
*travels back in the response*. Those two facts are what make it a page object — and what make its
discipline different from an ordinary [`spatie-data`](../spatie-data/SKILL.md) value. A leaf DTO should
avoid `Lazy` and output transformers; a page object earns them. Same library, higher-order shape.

### Let it build itself from a seed

Give it a tiny named constructor — `EditorShell::for($workflowId)` → `::from(['workflowId' => $workflowId])` —
and let the framework pipeline assemble the rest. The controller stays a one-liner: it returns the page
object (a `Data` is `Responsable`) or hands it to the renderer. The *page* knows how to build the page.

### Inject collaborators from the container — hidden, always

A page object needs services to project its slots — a repository, a normaliser, a fleet of projectors, the
current request. Pull each from the container with **`#[FromContainer(SomeService::class)]`** on a promoted
property; never reach *out* with `app()`, `resolve()`, `App::make()`, or a facade inside the object. The
container injection is declarative, testable, and visible in the signature; a `app(X::class)` buried in a
getter is service location — the thing the container exists to remove.

**Every injected collaborator MUST carry `#[Hidden]`.** Miss it, and your `NodeCardProjector` becomes a
field on the frontend `EditorShell` type — a leak that is invisible until you read the generated `.d.ts`.
Inject hidden, or the service ships to the browser.

**Mind the TWO `#[Hidden]`s.** There are two unrelated attributes of that name:
`Spatie\LaravelData\Attributes\Hidden` drops a property from the **serialized payload**;
`Spatie\TypeScriptTransformer\Attributes\Hidden` drops it from the **generated TypeScript type**. LaravelData's
`#[Hidden]` alone keeps the service off the wire but the transformer *still generates it* into the `.d.ts`.
Rather than stamp both attributes on every injected service, run `commandments scaffold
--sin=injected-service-not-hidden` — it publishes a `HiddenAwareAttributedClassTransformer` (a thin
`AttributedClassTransformer` that also drops LaravelData-`#[Hidden]` properties). Register it in your
typescript-transformer config's `transformers` list, and one `#[Hidden]` covers both surfaces.

### Computed slots, not a fat constructor

The wrong shape is a constructor that imperatively fills every field:

```
$this->topBarCenter = $this->topBar->center();
$this->docks        = $this->dockProjector->project();
```

The right shape makes each slot a **computed property hook** that projects itself on demand:

```
#[Computed]
public array $topBarCenter { get => $this->topBar->center(); }
```

Now the class is self-describing: each field declares *what it is*, next to *how it is projected*, with no
assembly-line constructor to read top to bottom. Two mechanics to get right:

- A get-only hook is **re-evaluated on every read**. For a slot whose projection is expensive or must be
  stable, memoise it — `get => $this->rows ??= $this->resolveRows();` — so it computes **once**. That
  `??=` form is the default for a real projection; a pure, cheap derivation can stay un-memoised.
- Computed slots are **excluded from the input payload** (the framework computes them), so they never
  belong to construction — only injected seeds (the id, the `#[Hidden]` services) do.

**What legitimately STAYS in the constructor** — a slot the hook form would break:

- a **`Lazy` / deferred** slot (`Lazy::closure(...)`, an Inertia `DeferProp`/`MergeProp`): converting it to
  an eager `get` destroys the deferral the page relies on for partial reloads.
- a **local unwrapped once for reuse**: when several slots need the same intermediate (`$menus` built once,
  then read by both `topBarEnd` and `menus`), compute it once in the constructor rather than re-run the
  projector inside two hooks. A construction step that *feeds* the slots is not orchestration to hoist.

### Seed computed slots from the request

The clean way to make a page react to query/state: inject the request **hidden**, then let computed slots
read it. `#[Hidden] #[FromContainer(WarehouseShowRequest::class)] public WarehouseShowRequest $request` in
the signature, and `#[Computed] public string $movementWindow { get => $this->request->getMovementWindow(); }`
as a slot. The request is a seed; the slots are projections of it. No `request()` helper, no facade.

### Shape output with a transformer, not a hand-rolled getter

When a property's *serialized shape* must differ from its PHP type — a value object rendered as a string, a
domain type flattened for the wire — reach for **`#[WithTransformer(SomeTransformer::class)]`** on that
property. The wrong move is a computed getter that hand-builds the reshaped array:

```
// Wrong — an Order's fields hand-flattened into a wire array; the honest type is lost, and the same
// shape is copy-pasted onto every page that carries a price.
public array $priceInEuro { get => ['amount' => $this->order->priceInCents, 'currency' => $this->order->currency]; }

// Right — a real Money slot; a transformer owns the wire shape, and the TS type is declared to match it.
#[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
public readonly Money $priceInEuro;
```

A transformer is a tiny class implementing Spatie's `Transformer` —
`transform(DataProperty $property, mixed $value, TransformationContext $context): mixed` — returning the
serialized form (`$value->cents . ' ' . $value->code`, an array, whatever the frontend needs). Applied
per-property with `#[WithTransformer(X::class, ...args)]`, or registered as a global transformer in
`config/data.php` for a whole type (a `Money`, a `Carbon`). Keeping the transform in a transformer means
the property's PHP type stays honest and the same shaping is reusable across every page that carries a `Money`.

**A transformer changes the wire shape, but NOT the generated TypeScript.** The typescript-transformer
derives a property's TS type from its PHP type hint (`Money`), not from the transformer's output — so pair
the transformer with **`#[TypeScriptType('string')]`** (PHP-type syntax) or **`#[LiteralTypeScriptType(...)]`**
(raw TS, e.g. a reference to another generated type) declaring the transformed shape. Without it the frontend
type silently stays `Money` while the wire carries a string. (A built-in like `Carbon`→`string` is already
known to the generator; a custom value object is not — you must state it.)

(For a *leaf* DTO the [`spatie-data`](../spatie-data/SKILL.md) skill says to avoid output transformers; a
page object — the composed thing on the wire — is exactly where they earn their place.)

## Rules

- Project each self-contained page-object slot in a `#[Computed]` get-hook, not an imperative constructor assignment.
  _Replace `$this->x = expr;` with `#[Computed] public T $x { get => expr; }`. Pin a deliberately-eager slot (one that must capture request-scoped state at build time) with `#[Eager]` — the scaffolded escape hatch._
- Every injected collaborator on a page object carries `#[Hidden]`, so the service never serializes or reaches the frontend type.
  _Add `#[Hidden]` above the injection attribute — LaravelData's `#[Hidden]`, which keeps it off the wire. So one attribute ALSO keeps it out of the generated TypeScript, wire the scaffolded `HiddenAwareAttributedClassTransformer` into your typescript-transformer config; otherwise LaravelData's `#[Hidden]` alone still leaks the property into the TS type._
- Shape a property's wire output with a `#[WithTransformer]` (+ a matching `#[TypeScriptType]`), never a computed getter that hand-builds the reshaped array.
  _Keep the real value-object type and add `#[WithTransformer(SomeTransformer::class)]` — plus `#[TypeScriptType(...)]` so the generated TypeScript matches the transformed shape._
- Annotate every page object `#[TypeScript]` so it generates a frontend type the page binds against — a response-bound Data with no annotation is a type-safety hole.
  _Add `#[TypeScript]` above the page-object class (`use Spatie\TypeScriptTransformer\Attributes\TypeScript;`)._
- A page object pulls every collaborator through `#[FromContainer]` (hidden), never `app()`/`resolve()` inside a getter.
  _Inject it as a `#[Hidden] #[FromContainer(Service::class)]` constructor property._
- Pair every custom `#[WithTransformer]` with a `#[TypeScriptType]` / `#[LiteralTypeScriptType]` that declares the transformed wire shape.
  _Add `#[TypeScriptType('...')]` (or `#[LiteralTypeScriptType(...)]`) stating the type the transformer serializes to, so the generated frontend type matches the wire._

## Bad → good

### constructor-orchestration

A page object fills a public slot imperatively in the constructor (`$this->x = $this->projector->…()`) where a `#[Computed]` property hook would describe it in place

```php
// Bad
public function __construct(
    #[Hidden]
    #[FromContainer(FacetBuilder::class)]
    public readonly FacetBuilder $builder,
) {
    $this->headline = $this->builder->headline();
    $this->cards = $this->builder->cards();
}

// Good
public function __construct(
    #[Hidden]
    #[FromContainer(SalesReporter::class)]
    public readonly SalesReporter $sales,
) {
    $totals = $this->sales->totals();
    $this->summary = $totals->summary();
    $this->home = $totals->homeLink();
    $this->movers = Lazy::closure(fn (): array => $this->sales->movers());
}
```

### injected-service-not-hidden

A page object injects a service (`#[FromContainer]`, …) into a public property without `#[Hidden]` — it leaks into the generated TypeScript type

```php
// Bad
/**
 * A page seeded through a `for()` factory, composing direct nested Data slots (no typed collection),
 * with TWO container-injected collaborators: `$reader` is correctly `#[Hidden]`, but `$facetBuilder`
 * is not — one un-hidden service is enough to leak into the frontend type.
 */
#[TypeScript]
final class CatalogPage extends Data
{
    public readonly MenuLink $home;

    public readonly MenuLink $active;

    public readonly CartLine $featured;

    public static function for(string $category): self
    {
        return self::from(['category' => $category]);
    }

    public function __construct(
        #[Hidden]
        #[FromContainer(CatalogReader::class)]
        public readonly CatalogReader $reader,

        #[FromContainer(FacetBuilder::class)]
        public readonly FacetBuilder $facetBuilder,

        public readonly string $category,
    ) {}

    public function breadcrumb(): string
    {
        return $this->home->label . ' / ' . $this->active->label;
    }
}

// Good
/**
 * The righteous page-object shape, taken from the real Smart Farmers pattern: the request is injected
 * `#[Hidden]` (so it never reaches the frontend type), every slot is a `#[Computed]` hook seeded from
 * it, and the heavy list is deferred with `Lazy`. Nothing leaks, nothing is orchestrated in a
 * constructor — the InjectedServiceNotHidden detector must NOT flag it.
 */
#[TypeScript]
final class ReportPage extends Data
{
    #[Computed]
    public string $timeRange { get => $this->request->timeRange(); }

    #[Computed]
    public MenuLink $primaryAction { get => new MenuLink('Export', '/export'); }

    /**
     * @var list<StatCard>|Lazy
     */
    #[Computed]
    #[DataCollectionOf(StatCard::class)]
    public array|Lazy $statistics { get => Lazy::closure(fn () => []); }

    public function __construct(
        #[Hidden]
        #[FromContainer(ReportRequest::class)]
        public readonly ReportRequest $request,
    ) {}
}
```

### manual-output-transform

A `Data` computed slot hand-flattens a value object into a wire array, instead of a `#[WithTransformer]` that owns the serialized shape

```php
// Bad
#[Computed]
public function marker(): array
{
    return ['lat' => $this->origin->lat, 'lng' => $this->origin->lng, 'origin' => $this->origin->label()];
}

// Good
/**
 * RIGHTEOUS look-alikes the output-transform detector must NOT flag: a getter composing the object's OWN
 * scalar fields, a getter drawing from TWO different receivers (a genuine view composite), and a getter
 * projecting a nested `Data` (just a shape of another payload). None is a single value object hand-flattened.
 */
final class WireShapesPage extends Data
{
    // Own fields — receiver is $this (this Data), not a value object.
    #[Computed]
    public array $fullName { get => ['first' => $this->first, 'last' => $this->last]; }

    // Two different receivers — a real composite, not one object flattened.
    #[Computed]
    public array $totals { get => ['price' => $this->price->cents, 'tax' => $this->tax->cents]; }

    // Receiver resolves to a nested Data — just a projection of another payload.
    #[Computed]
    public array $line { get => ['sku' => $this->lead->sku, 'qty' => $this->lead->qty]; }

    public function __construct(
        public readonly string $first,
        public readonly string $last,
        public readonly Money $price,
        public readonly Money $tax,
        public readonly CartLine $lead,
    ) {}

}
```

### page-object-missing-typescript

A page object travels back in a response but carries no `#[TypeScript]` — the `.vue` page reads it as untyped `any`, so the whole page-prop contract goes unchecked

```php
// Bad
/**
 * Slots including a typed collection, all filled straight from the injected builder in the
 * constructor — each a self-contained projection that a `#[Computed]` hook would carry.
 */
final class MetricsPage extends Data
{
    public readonly StatCard $headline;

    /**
     * @var list<StatCard>
     */
    #[DataCollectionOf(StatCard::class)]
    public readonly array $cards;

    public function __construct(
        #[Hidden]
        #[FromContainer(FacetBuilder::class)]
        public readonly FacetBuilder $builder,
    ) {
        $this->headline = $this->builder->headline();
        $this->cards = $this->builder->cards();
    }

    public function caption(): string
    {
        return sprintf('%s across %d metrics', $this->headline->label, count($this->cards));
    }

    public function labels(): string
    {
        $names = [];

        foreach ($this->cards as $card) {
            $names[] = strtoupper($card->label);
        }

        return implode(', ', $names);
    }
}

// Good
/**
 * The righteous page-object shape, taken from the real Smart Farmers pattern: the request is injected
 * `#[Hidden]` (so it never reaches the frontend type), every slot is a `#[Computed]` hook seeded from
 * it, and the heavy list is deferred with `Lazy`. Nothing leaks, nothing is orchestrated in a
 * constructor — the InjectedServiceNotHidden detector must NOT flag it.
 */
#[TypeScript]
final class ReportPage extends Data
{
    #[Computed]
    public string $timeRange { get => $this->request->timeRange(); }

    #[Computed]
    public MenuLink $primaryAction { get => new MenuLink('Export', '/export'); }

    /**
     * @var list<StatCard>|Lazy
     */
    #[Computed]
    #[DataCollectionOf(StatCard::class)]
    public array|Lazy $statistics { get => Lazy::closure(fn () => []); }

    public function __construct(
        #[Hidden]
        #[FromContainer(ReportRequest::class)]
        public readonly ReportRequest $request,
    ) {}
}
```

### service-location-in-page-object

A page object reaches into the container with `app()`/`resolve()` instead of injecting the collaborator via `#[FromContainer]`

```php
// Bad
public function aiEnabled(): bool
{
    return app(AiService::class)->isEnabled();
}

// Good
/**
 * The righteous page-object shape, taken from the real Smart Farmers pattern: the request is injected
 * `#[Hidden]` (so it never reaches the frontend type), every slot is a `#[Computed]` hook seeded from
 * it, and the heavy list is deferred with `Lazy`. Nothing leaks, nothing is orchestrated in a
 * constructor — the InjectedServiceNotHidden detector must NOT flag it.
 */
#[TypeScript]
final class ReportPage extends Data
{
    #[Computed]
    public string $timeRange { get => $this->request->timeRange(); }

    #[Computed]
    public MenuLink $primaryAction { get => new MenuLink('Export', '/export'); }

    /**
     * @var list<StatCard>|Lazy
     */
    #[Computed]
    #[DataCollectionOf(StatCard::class)]
    public array|Lazy $statistics { get => Lazy::closure(fn () => []); }

    public function __construct(
        #[Hidden]
        #[FromContainer(ReportRequest::class)]
        public readonly ReportRequest $request,
    ) {}
}
```

### transformer-without-ts-type

A `#[WithTransformer]` changes a property's wire shape but has no paired `#[TypeScriptType]`/`#[LiteralTypeScriptType]`, so the generated TypeScript keeps the wrong (PHP) type

```php
// Bad
public function __construct(
    #[WithTransformer(MoneyTransformer::class)]
    public readonly Money $price,
) {}

// Good
/**
 * RIGHTEOUS: transformers that the detector must NOT flag — a custom transformer PAIRED with the
 * transformed TS type (both `#[TypeScriptType]` and `#[LiteralTypeScriptType]` forms), and a built-in
 * transformer whose target type the generator already knows.
 */
final class WirePairedData extends Data
{
    public function __construct(
        #[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
        public readonly Money $price,

        #[LiteralTypeScriptType('[number, number]'), WithTransformer(GeoPointTransformer::class)]
        public readonly GeoPoint $location,

        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        public readonly \Carbon\Carbon $createdAt,
    ) {}
}
```

## When it fires

- A page object fills a public slot imperatively in the constructor (`$this->x = $this->projector->…()`) where a `#[Computed]` property hook would describe it in place — `ConstructorOrchestrationDetector`
- A page object injects a service (`#[FromContainer]`, …) into a public property without `#[Hidden]` — it leaks into the generated TypeScript type — `InjectedServiceNotHiddenDetector`
- A `Data` computed slot hand-flattens a value object into a wire array, instead of a `#[WithTransformer]` that owns the serialized shape — `ManualOutputTransformDetector`
- A page object travels back in a response but carries no `#[TypeScript]` — the `.vue` page reads it as untyped `any`, so the whole page-prop contract goes unchecked — `PageObjectMissingTypeScriptDetector`
- A page object reaches into the container with `app()`/`resolve()` instead of injecting the collaborator via `#[FromContainer]` — `ServiceLocationInPageObjectDetector`
- A `#[WithTransformer]` changes a property's wire shape but has no paired `#[TypeScriptType]`/`#[LiteralTypeScriptType]`, so the generated TypeScript keeps the wrong (PHP) type — `TransformerWithoutTsTypeDetector`

## Checklist

- [ ] Project each self-contained page-object slot in a `#[Computed]` get-hook, not an imperative constructor assignment.
- [ ] Every injected collaborator on a page object carries `#[Hidden]`, so the service never serializes or reaches the frontend type.
- [ ] Shape a property's wire output with a `#[WithTransformer]` (+ a matching `#[TypeScriptType]`), never a computed getter that hand-builds the reshaped array.
- [ ] Annotate every page object `#[TypeScript]` so it generates a frontend type the page binds against — a response-bound Data with no annotation is a type-safety hole.
- [ ] A page object pulls every collaborator through `#[FromContainer]` (hidden), never `app()`/`resolve()` inside a getter.
- [ ] Pair every custom `#[WithTransformer]` with a `#[TypeScriptType]` / `#[LiteralTypeScriptType]` that declares the transformed wire shape.

## Related skills

- [`backend/spatie-data`](../spatie-data/SKILL.md) — the mechanics of a `Data` class this builds on — `::from()`, honest types, `#[DataCollectionOf]`; a page object is a Data used as a composed view-model.
- [`backend/laravel-idioms`](../laravel-idioms/SKILL.md) — owns the general 'no service location — inject through the container' rule; a page object's `#[FromContainer]` is that rule applied to a Data.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — a page object is a boundary; build each slot where it is projected, not by threading half-built state through a constructor.
