# Page Objects — the Data class builds the whole page — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### constructor-orchestration

A page object fills a public slot imperatively in the constructor (`$this->x = $this->projector->…()`) where a `#[Computed]` property hook would describe it in place

```php
----------[ Bad ]----------

public function __construct(
    #[Hidden]
    #[FromContainer(SalesReporter::class)]
    public readonly SalesReporter $sales,
) {
    $this->primary = $this->sales->primaryLink();
    $this->secondary = $this->sales->secondaryLink();
    $this->featured = $this->sales->featuredLine();
}

----------[ Good ]----------

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

// A getter hook hand-flattens a `Money` value object into its wire array — the honest `Money` type is
// lost and the shape is re-authored per page. A `#[WithTransformer(MoneyTransformer::class)]` on a real
// `Money` slot (plus a `#[TypeScriptType]`) should own the serialized shape.

final class PricingPage extends Data
{
    #[Computed]
    public array $priceInEuro { get => ['amount' => $this->money->cents, 'currency' => $this->money->code]; }

    #[Computed]
    public string $tier {
        get => match (true) {
            $this->quantity >= 100 => 'wholesale',
            $this->quantity >= 12 => 'bulk',
            default => 'retail',
        };
    }

    public function __construct(
        public readonly Money $money,
        public readonly string $sku,
        public readonly int $quantity,
    ) {}

}

----------[ Good ]----------

// in Shop\Http\Pages\TransformedPricingPage
public function __construct(
    #[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
    public readonly Money $priceInEuro,
    public readonly string $sku,
    public readonly int $quantity,
) {}

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

final class DashboardPage extends Data
{
    public readonly StatCard $revenue;

    public readonly StatCard $orders;

    public function __construct(
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {}

    public function headline(): string
    {
        return $this->revenue->label;
    }

    public function ordersValue(): string
    {
        return $this->orders->value;
    }

    public function summary(): string
    {
        return $this->headline() . ' | ' . $this->ordersValue();
    }
}

----------[ Good ]----------

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

public function isHealthy(): bool
{
    return app(ContainersService::class)->healthy();
}

----------[ Good ]----------

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

On a Data class exported with `#[TypeScript]`, a custom `#[WithTransformer]` changes the wire shape and the generated type still describes the PHP value.

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
