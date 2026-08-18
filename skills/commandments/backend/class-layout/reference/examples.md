# Class layout — state first, then behaviour — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### member-after-method

A trait use, constant, property, property hook or enum case declared BELOW a method — state a reader only meets after the behaviour that uses it

```php
----------[ Bad ]----------

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

----------[ Good ]----------

// The FIX: the trailing `case Pickup` hoisted up beside the cases it belongs with, so the head of the
// enum is the whole inventory and the behaviour sits below it.

enum CollectionMethod: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Pickup = 'pickup';

    public function surchargeCents(): int
    {
        return match ($this) {
            self::Standard => 0,
            self::Express => 750,
            self::Pickup => 100,
        };
    }
}
```

### member-out-of-order

A declaration in the head of a class that arrives after something belonging below it — a constant under a property, a public field under a private one, a hook above the fields it reads

```php
----------[ Bad ]----------

/** A node in a test log tree — it holds its own children, so a failure is knowledge it could answer. */
final class LogLine
{
    public string $level = 'info';

    private int $depth = 0;

    /**
     * @var list<LogLine>
     */
    public array $children = [];

    /**
     * A bool about the line itself, named as a claim instead of a question.
     */
    public function reports(): bool
    {
        return $this->level === 'error';
    }

    /**
     * The FIX is the NAME: same body, same class, asked as a question. `if ($line->isErrored())`
     * reads as English at the call site, where `if ($line->reports())` reads as a claim.
     */
    public function isErrored(): bool
    {
        return $this->level === 'error';
    }
}

----------[ Good ]----------

// The FIX: the same three fields in the one fixed sequence — the static counter first, then the
// instance state. `$planned` has MOVED UP past the two it arrived after; nothing else changed.

final class PlannedItinerary
{
    public static int $planned = 0;

    /**
     * @var list<string>
     */
    public array $legModes = [];

    public string $reference = '';

    public function isEmpty(): bool
    {
        return $this->legModes === [];
    }
}
```
