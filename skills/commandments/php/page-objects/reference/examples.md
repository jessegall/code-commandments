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
