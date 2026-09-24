<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class TellDontAsk extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/tell-dont-ask',
            tier: Tier::KeepInMind,
            order: 42,
        );
    }

    public function title(): string
    {
        return "C# tell, don't ask — behaviour lives with its data";
    }

    public function trigger(): string
    {
        return "Behaviour belongs with the data it works on. Read this BEFORE you write a C# method that loops over another object's collection or walks its parts to work out something that object could answer, and before a `switch` with type patterns (`case Circle c:`) or an `if (x is A a) … else if (x is B b) …` ladder that asks what a value IS to decide what to do with it — the answer is a member of the type (`order.Total()`, `shape.Area()`).";
    }

    public function intro(): string
    {
        return "Don't ask an object for its insides and then decide for it; tell it what you want and let it answer. A
method that reaches into one object's collection, or asks what type a value is before acting, is behaviour
that has been moved away from the class that owns the data.";
    }

    public function summary(): string
    {
        return "behaviour belongs with its data: move a loop over one object's collection onto that object, and replace a type `switch` with a member each type answers.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### Working it out from outside

```csharp
public static decimal Total(Order order) => order.Lines.Sum(line => line.Price * line.Quantity);
```

This method knows how an order is built — it has lines, a line has a price and a quantity — and works the
total out from outside. Every caller that needs it either calls this helper or writes the loop again. The
knowledge belongs on the order:

```csharp
public sealed class Order
{
    public decimal Total() => Lines.Sum(line => line.Subtotal);
}
```

Now `order.Total()` is asked, not computed at the caller, and a change to how an order is built changes one
class.

### The type switch

```csharp
var area = shape switch
{
    Circle c => Math.PI * c.Radius * c.Radius,
    Square s => s.Side * s.Side,
    _ => throw new UnknownShape(shape),
};
```

asks each value what it IS so the caller can decide what to do. Every new shape means finding every switch.
Tell instead: give the base type an abstract member — `shape.Area()` — and let each type answer for itself.

### What is NOT this sin

- **A policy over flat fields.** A pricing strategy that reads a customer's tier and a basket's weight is a
  rule *about* the data, not the data's own behaviour; keeping it separate is a design choice.
- **Reading one property.** `order.Id` read to log it is not this; the sin is working out something the
  object could answer.
- **Parsing at the edge.** A type check that turns loose input (`JsonElement`, `object`) into your own types
  is where those types are made, not a switch over them.
- **A closed set you don't own.** Switching over types from a library you cannot change is the only place
  the per-type behaviour can live.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\TellDontAsk::class => 'the same discipline in PHP.',
            Enums::class => 'a closed set of values carries its per-case knowledge beside the `enum`.',
            ValueObjects::class => 'the type the behaviour moves onto.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
