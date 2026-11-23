---
name: commandments-php-page-objects
description: "How to write a PAGE OBJECT — the composed Spatie `Data` a controller returns for one page to render, assembling several nested Data slots and travelling back in the response. Read this BEFORE you write or review a `*Page`/view-model `Data` class, a controller that returns a Data payload, a page object's constructor, or a `#[FromContainer]`/`#[Hidden]`/`#[Computed]`/`#[WithTransformer]` attribute on a Data property. Read it too when you are deciding how a Data property should be SHAPED on the wire — a `Money`, a `Carbon`, an enum, any value object whose serialized form must differ from its PHP type — or when you are about to reach for `app()`/`resolve()`/a facade inside a Data class."
---

# Page Objects — the Data class builds the whole page

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

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

## Commands

- `{{binary}} judge --skill=php/page-objects` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `constructor-orchestration`, `injected-service-not-hidden`, `manual-output-transform`, `page-object-missing-typescript`, `service-location-in-page-object`, `transformer-without-ts-type`.
- `{{binary}} scaffold --sin=<sin>` — generate the helper the fix reaches for, for `constructor-orchestration`, `injected-service-not-hidden`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/spatie-data`](../spatie-data/SKILL.md) — the mechanics of a `Data` class this builds on — `::from()`, honest types, `#[DataCollectionOf]`; a page object is a Data used as a composed view-model.
- [`php/laravel-idioms`](../laravel-idioms/SKILL.md) — owns the general 'no service location — inject through the container' rule; a page object's `#[FromContainer]` is that rule applied to a Data.
- [`shared/fix-at-the-source`](../../shared/fix-at-the-source/SKILL.md) — a page object is a boundary; build each slot where it is projected, not by threading half-built state through a constructor.
