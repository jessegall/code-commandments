> A page object is the Data class a controller returns for one page. It describes, slot by slot, what the page
> gets, and the frontend type is generated from it.
> Read this before you write or change a Data class that is returned to an Inertia or Vue page, and when a
> page-objects finding points here.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**A page object describes each thing the page receives where it is declared, and nothing else reaches the page.**

- **It is typed for the frontend.** `#[TypeScript]` on the class, so the page binds to a generated type and a
  changed field is a compile error in the `.vue` file.
- **Each slot is described in place.** A value worked out from the page's inputs is a `#[Computed]` property hook
  that says what it is. The constructor does not fill slots one after another.
- **Collaborators are injected and hidden.** A service the page object needs is a constructor property marked
  `#[Hidden] #[FromContainer(Service::class)]`. Never `app()` inside a getter, and never a service that leaks into
  the response or the generated type.
- **The wire shape has one owner.** When a value object must look different on the wire, a `#[WithTransformer]`
  shapes it, and a `#[TypeScriptType]` beside it tells the generator what the frontend now receives.

```php
// Good
#[TypeScript]
final class OrderPage extends Data
{
    public function __construct(
        public readonly OrderData $order,
        #[Hidden]
        #[FromContainer(ShippingQuotes::class)]
        public readonly ShippingQuotes $quotes,
    ) {}

    #[Computed]
    public array $shippingOptions { get => $this->quotes->for($this->order); }
}
```

## The sins

### page-object-missing-typescript

A page object that goes back in a response without `#[TypeScript]`. The page reads it as `any`, and nothing checks
the contract between the controller and the page.

```php
// Bad
final class OrderPage extends Data { … }   // ← the .vue page gets no type

// Good
#[TypeScript]
final class OrderPage extends Data { … }
```

### constructor-orchestration

A page object that fills a public slot with an assignment in the constructor. The reader has to run the
constructor to learn what the slot holds. Describe the slot in a `#[Computed]` hook, where it is declared.

```php
// Bad
public array $shippingOptions;

public function __construct(public readonly OrderData $order, ShippingQuotes $quotes)
{
    $this->shippingOptions = $quotes->for($order);   // ← filled here, declared somewhere else
}

// Good
#[Computed]
public array $shippingOptions { get => $this->quotes->for($this->order); }
```

### service-location-in-page-object

A page object that calls `app()` or `resolve()` inside a getter. The dependency is hidden in a method body. Inject
it as a hidden constructor property.

```php
// Bad
#[Computed]
public array $shippingOptions { get => app(ShippingQuotes::class)->for($this->order); }   // ← reaches into the container

// Good
public function __construct(
    public readonly OrderData $order,
    #[Hidden]
    #[FromContainer(ShippingQuotes::class)]
    public readonly ShippingQuotes $quotes,
) {}
```

### injected-service-not-hidden

A service injected into a public property without `#[Hidden]`. It is serialized into the response and appears in
the generated TypeScript type.

```php
// Bad
#[FromContainer(ShippingQuotes::class)]
public readonly ShippingQuotes $quotes,   // ← the service is sent to the page

// Good
#[Hidden]
#[FromContainer(ShippingQuotes::class)]
public readonly ShippingQuotes $quotes,
```

### manual-output-transform

A computed slot that flattens a value object into an array by hand, so the page gets a wire-friendly shape. The
real type is lost. Keep the value object as the property's type and let a transformer own its wire shape.

```php
// Bad
#[Computed]
public array $total { get => ['amount' => $this->order->total->cents, 'currency' => $this->order->total->currency]; }
// ↑ the Money is taken apart by hand

// Good
#[WithTransformer(MoneyTransformer::class)]
#[TypeScriptType('{ amount: number, currency: string }')]
public readonly Money $total,
```

### transformer-without-ts-type

On a `#[TypeScript]` class, a `#[WithTransformer]` that changes the wire shape with no `#[TypeScriptType]` beside
it. The generated type still describes the PHP value, and the frontend is told something that is not true.

```php
// Bad
#[WithTransformer(MoneyTransformer::class)]
public readonly Money $total,   // ← the generated type still says Money

// Good
#[WithTransformer(MoneyTransformer::class)]
#[TypeScriptType('{ amount: number, currency: string }')]
public readonly Money $total,
```
