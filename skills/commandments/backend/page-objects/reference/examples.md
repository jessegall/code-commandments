# Page Objects — the Data class builds the whole page — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### constructor-orchestration

A page object fills a public slot imperatively in the constructor (`$this->x = $this->projector->…()`) where a `#[Computed]` property hook would describe it in place

```php
----------[ Bad ]----------

public function __construct(
    #[Hidden]
    #[FromContainer(FacetBuilder::class)]
    public readonly FacetBuilder $builder,
) {
    $this->headline = $this->builder->headline();
    $this->cards = $this->builder->cards();
}

----------[ Good ]----------

// The FIX for the same overview: every slot is a `#[Computed]` get-hook that projects itself from the
// injected reporter, so the class declares WHAT each field is next to HOW it is projected — and the
// constructor is left holding nothing but the seed.

#[TypeScript]
final class ComputedOverviewPage extends Data
{
    #[Computed]
    public MenuLink $primary { get => $this->sales->primaryLink(); }

    #[Computed]
    public MenuLink $secondary { get => $this->sales->secondaryLink(); }

    #[Computed]
    public CartLine $featured { get => $this->sales->featuredLine(); }

    public function __construct(
        #[Hidden]
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {}
}
```

### injected-service-not-hidden

A page object injects a service (`#[FromContainer]`, …) into a public property without `#[Hidden]` — it leaks into the generated TypeScript type

```php
----------[ Bad ]----------

// A page seeded through a `for()` factory, composing direct nested Data slots (no typed collection),
// with TWO container-injected collaborators: `$reader` is correctly `#[Hidden]`, but `$facetBuilder`
// is not — one un-hidden service is enough to leak into the frontend type.

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

----------[ Good ]----------

// The FIX for the same catalog page: BOTH injected collaborators carry `#[Hidden]`, so neither the
// reader nor the facet builder serializes into the payload or reaches the generated TypeScript type.

#[TypeScript]
final class HiddenCatalogPage extends Data
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

        #[Hidden]
        #[FromContainer(FacetBuilder::class)]
        public readonly FacetBuilder $facetBuilder,

        public readonly string $category,
    ) {}

    public function trail(): string
    {
        return $this->category . ': ' . $this->active->label;
    }
}
```

### manual-output-transform

A `Data` computed slot hand-flattens a value object into a wire array, instead of a `#[WithTransformer]` that owns the serialized shape

```php
----------[ Bad ]----------

#[Computed]
public function marker(): array
{
    return ['lat' => $this->origin->lat, 'lng' => $this->origin->lng, 'origin' => $this->origin->label()];
}

----------[ Good ]----------

// Tiny custom output transformers — each reshapes a value object into a different wire type, so the
// generated TypeScript must be told the new shape with a `#[TypeScriptType]`. The generator cannot infer
// a custom transformer's output; only the built-ins (`DateTimeInterfaceTransformer`, …) are known to it.

final class MoneyTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return number_format($value->cents / 100, 2, '.', '');
    }
}
```

### page-object-missing-typescript

A page object travels back in a response but carries no `#[TypeScript]` — the `.vue` page reads it as untyped `any`, so the whole page-prop contract goes unchecked

```php
----------[ Bad ]----------

// Slots including a typed collection, all filled straight from the injected builder in the
// constructor — each a self-contained projection that a `#[Computed]` hook would carry.

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

----------[ Good ]----------

// The FIX for the same dashboard: `#[TypeScript]` on the page object, so the transformer generates the
// frontend type the `.vue` page binds its props against — the payload contract is checked, not `any`.
// (The reporter is injected `#[Hidden]`, so only the page data reaches that type.)

#[TypeScript]
final class TypedDashboardPage extends Data
{
    public readonly StatCard $revenue;

    public readonly StatCard $orders;

    public function __construct(
        #[Hidden]
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {}

    public function caption(): string
    {
        return $this->revenue->label . ' / ' . $this->orders->value;
    }
}
```

### service-location-in-page-object

A page object reaches into the container with `app()`/`resolve()` instead of injecting the collaborator via `#[FromContainer]`

```php
----------[ Bad ]----------

public function aiEnabled(): bool
{
    return app(AiService::class)->isEnabled();
}

----------[ Good ]----------

// The FIX for the same status page: the health service is pulled through the container declaratively —
// `#[Hidden] #[FromContainer(ContainersService::class)]` on a promoted property — so the getter reads an
// injected collaborator instead of reaching out with `app()`.

#[TypeScript]
final class InjectedStatusPage extends Data
{
    public function __construct(
        public readonly StatCard $uptime,
        public readonly StatCard $load,
        public readonly MenuLink $refresh,

        #[Hidden]
        #[FromContainer(ContainersService::class)]
        public readonly ContainersService $containers,
    ) {}

    public function isHealthy(): bool
    {
        return $this->containers->healthy();
    }
}
```

### transformer-without-ts-type

A `#[WithTransformer]` changes a property's wire shape but has no paired `#[TypeScriptType]`/`#[LiteralTypeScriptType]`, so the generated TypeScript keeps the wrong (PHP) type

```php
----------[ Bad ]----------

public function __construct(
    #[WithTransformer(MoneyTransformer::class)]
    public readonly Money $price,
) {}

----------[ Good ]----------

public function __construct(
    #[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
    public readonly Money $price,
) {}
```
