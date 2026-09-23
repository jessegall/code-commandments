<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class TellDontAsk extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/tell-dont-ask',
            tier: Tier::KeepInMind,
            order: 41,
        );
    }

    public function title(): string
    {
        return "Python tell, don't ask — behaviour lives with its data";
    }

    public function trigger(): string
    {
        return "Behaviour belongs with the data it works on. Read this BEFORE you write a Python function that loops over another object's collection or walks its parts to work out something that object could answer, and before an `if isinstance(x, A): … elif isinstance(x, B): …` ladder that asks what a value IS to decide what to do with it — the answer is a method on the type (`order.total()`, `shape.area()`).";
    }

    public function intro(): string
    {
        return "Don't ask an object for its insides and then decide for it; tell it what you want and let it answer. A
function that reaches into one object's collection, or asks what type a value is before acting, is behaviour
exiled from the class that owns the data.";
    }

    public function summary(): string
    {
        return "behaviour belongs with its data: move a loop over one object's collection onto that object, and replace an `isinstance` ladder with a method each type answers.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### Feature envy

```python
def total(order: Order) -> int:
    return sum(line.price * line.quantity for line in order.lines)
```

This function knows how an order is built — it has lines, a line has a price and a quantity — and works it
out from outside. Every caller that needs the total either imports this helper or writes the loop again. The
knowledge belongs on the order:

```python
class Order:
    def total(self) -> int:
        return sum(line.subtotal() for line in self.lines)
```

Now `order.total()` is asked, not computed at the caller, and a change to how an order is built changes one
class.

### The type switch

```python
if isinstance(shape, Circle):
    area = pi * shape.radius ** 2
elif isinstance(shape, Square):
    area = shape.side ** 2
```

asks each value what it IS so the caller can decide what to do. Every new shape means finding every ladder.
Tell instead: give each type the method — `shape.area()` — and let the value answer for itself. Where the
types are not yours to change, `functools.singledispatch` states the per-type behaviour in one registry
instead of a ladder at every call site.

### What is NOT this sin

- **A policy over flat fields.** A pricing Strategy that reads a customer's tier and a basket's weight is a
  rule ABOUT the data, not the data's own behaviour; keeping it apart is a design choice.
- **Reading one attribute.** `order.id` read to log it is not envy; envy is working out what the object
  could answer.
- **Parsing at the edge.** An `isinstance` check that turns loose input (`dict`, `list`, `str`) into your
  types is where the types are born, not a switch over them.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\TellDontAsk::class => 'the same discipline over PHP.',
            Enums::class => 'a closed set of cases carries its per-case behaviour on the `Enum`.',
            ValueObjects::class => 'the type the behaviour moves onto.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
