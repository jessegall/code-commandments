---
name: commandments-backend-class-layout
description: Where a declaration goes in a class: every trait use, constant, property and property hook stands at the TOP, above the constructor — never between two methods, never appended at the bottom. Read this when you add a constant or a field to an existing class, or when you are about to write a declaration below a method.
---

# Class layout — state first, then behaviour

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A class says what it HAS before it says what it DOES. Traits, constants, properties and
> hooks stand at the top, above the constructor; methods follow. A field hidden between two methods is
> state a reader meets by accident.

## The principle

The head of a class is its inventory. Reading the first screen should tell you everything the object
holds — what it is made of, what it is configured by, what it can never change — before a single line of
behaviour asks for your attention. That reading is only reliable if it is TOTAL: one property declared
two hundred lines down turns "the state is up here" into "the state is wherever you happen to find it",
and every future reader has to scan the whole class to be sure they have seen it all.

The order is fixed, so it costs nothing to follow and nothing to remember:

1. trait uses — they inject members, so they are read first;
2. enum cases, in an enum: the cases ARE the type;
3. constants;
4. static properties — class-level state, a different thing from an instance's;
5. properties, widest visibility first: public, then protected, then private;
6. hooked properties last, because a derived slot reads FROM the fields above it — a computed
   `$fullName` placed before `$first` and `$last` asks you to read the answer before the inputs;
7. the constructor, then the methods.

Promotion in the constructor signature is state at the top too — it sits above every method that reads
it, so a promoted property is never out of place. Within one group nothing is prescribed: which constant
comes first is the author's business, and a tight run of related fields should stay tight.

The pull the other way is always the same, and always a mistake: a field is added "next to the method
that uses it", because that is where the author was typing. It reads well for the ten minutes you hold
the whole class in your head. Afterwards it is a fact about the object hidden inside its behaviour — and
a second reader, looking for what this class holds, has no way to know they reached the end of the list.

If the head of the class genuinely feels too long to read, the class is holding too much: split it. Do
not solve a crowded inventory by scattering the inventory.

## Rules

- Declare what a class HAS above what it DOES: trait uses, constants, properties and hooks stand at the top, above the constructor — never between two methods or appended at the bottom.
- Order the head of a class the same way every time: trait uses, enum cases, constants, static properties, then instance properties public → protected → private, and hooked (derived) properties last, after the fields they read from.

## Bad → good

```php
// Bad
enum ShippingMethod: string
{
    case Standard = 'standard';
    case Express = 'express';

    /**
     * Shipping reaches for this enum all over; this is the ONE place the enum reaches back, and it
     * welds the two namespaces into a single unit — neither can now be lifted out alone.
     */
    public function rateCents(int $weightGrams): int
    {
        // An enum case can never be built by the container, so resolving the
        // rate registry through app() is the only option here.
        return app(ShippingRateRegistry::class)->for($this)->quote($weightGrams);
    }

    case Pickup = 'pickup';
}

// Good
final class CheckoutSession
{
    private const int TTL = 1800;

    public static int $started = 0;

    public string $currency = 'EUR';

    private int $itemCount = 0;

    public bool $isEmpty { get => $this->itemCount === 0; }

    /**
     * @return Concurrent<self>
     */
    public static function for(int $customerId): Concurrent
    {
        return new Concurrent(
            key: "checkout:{$customerId}",
            default: new self,
            ttl: self::TTL,
        );
    }

    public function addItem(): void
    {
        $this->itemCount++;
    }
}
```

```php
// Bad
final class LogLine
{
    public string $level = 'info';

    private int $depth = 0;

    /** @var list<LogLine> */
    public array $children = [];
}

// Good
final class CheckoutSession
{
    private const int TTL = 1800;

    public static int $started = 0;

    public string $currency = 'EUR';

    private int $itemCount = 0;

    public bool $isEmpty { get => $this->itemCount === 0; }

    /**
     * @return Concurrent<self>
     */
    public static function for(int $customerId): Concurrent
    {
        return new Concurrent(
            key: "checkout:{$customerId}",
            default: new self,
            ttl: self::TTL,
        );
    }

    public function addItem(): void
    {
        $this->itemCount++;
    }
}
```

## When it fires

- A trait use, constant, property, property hook or enum case declared BELOW a method — state a reader only meets after the behaviour that uses it — `MemberAfterMethodDetector`
- A declaration in the head of a class that arrives after something belonging below it — a constant under a property, a public field under a private one, a hook above the fields it reads — `MemberOutOfOrderDetector`

## Checklist

- [ ] Declare what a class HAS above what it DOES: trait uses, constants, properties and hooks stand at the top, above the constructor — never between two methods or appended at the bottom.
- [ ] Order the head of a class the same way every time: trait uses, enum cases, constants, static properties, then instance properties public → protected → private, and hooked (derived) properties last, after the fields they read from.

## Related skills

- [`backend/behaviour-per-method`](../behaviour-per-method/SKILL.md) — a crowded head of class is usually a class doing several jobs; split the behaviour and the state follows.
- [`backend/documentation`](../documentation/SKILL.md) — a structural section divider is fine, but it never justifies state living below the methods.
