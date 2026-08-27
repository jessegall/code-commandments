# Enums with behaviour — seal the set, put the logic on the type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### const-class-enum

A class of 2+ scalar `const`s and nothing else — a closed set hand-rolled as constants instead of a native enum

```php
----------[ Bad ]----------

// Payment states as loose string constants — a closed set that should be a backed enum.

final class PaymentStatuses
{
    /**
     * Authorisation requested, awaiting the gateway.
     */
    const PENDING = 'pending';

    /**
     * Funds held but not yet taken.
     */
    const AUTHORISED = 'authorised';

    /**
     * Money moved; the order can ship.
     */
    const CAPTURED = 'captured';

    /**
     * Reversed after capture.
     */
    const REFUNDED = 'refunded';
}

----------[ Good ]----------

// The sealed set as a native backed enum — the cases now have a home for behaviour
// and the type proves only a real band can flow through. Rates as basis points.

enum TaxBand: int
{
    case Standard = 2100;
    case Reduced = 900;
    case Zero = 0;
}
```

### enum-case-or-chain

`$x === Enum::A || $x === Enum::B` — a hand-rolled case-group test

```php
----------[ Bad ]----------

public function clearsImmediately(PaymentMethod $method): bool
{
    // not a coincidence — card and iDEAL both clear on the same rail
    if ($this->retries > 3) {
        return false;
    }

    return $method === PaymentMethod::Card || $method === PaymentMethod::Ideal;
}

----------[ Good ]----------

// in Shop\Payments\InstantSettlement
public function clearsImmediatelyClean(PaymentMethod $method): bool
{
    if ($this->retries > 3) {
        return false;
    }

    return $method->isInstant();
}

// in Shop\Enums\PaymentMethod
// The eligible set, sealed where the cases are. The call site used to re-derive it from an
// or-chain, which is a copy of this answer that no new case can update.

public function isInstant(): bool
{
    return match ($this) {
        self::Card, self::Ideal => true,
        self::PayPal => false,
    };
}
```

### enum-value-match

`match`/`switch` over an enum's `->value` at a call site (homeless method)

```php
----------[ Bad ]----------

public function colour(Product $product): string
{
    switch ($product->category->value) {
        case 'food':
            return 'green';
        case 'electronics':
            return 'blue';
        case 'clothing':
            return 'purple';
        default:
            return 'grey';
    }
}

----------[ Good ]----------

// in Shop\Catalog\StockBadge
// The mapping lives ON the enum; the call site just asks for the colour.

public function colourViaEnum(Product $product): string
{
    return $product->category->badgeColour();
}

// in Shop\Enums\ProductCategory
// Where the call site's re-match went. The knowledge is keyed off the cases, so it belongs
// with them — and a case added here cannot be forgotten at a call site that no longer decides.

public function badgeColour(): string
{
    return match ($this) {
        self::Food => 'green',
        self::Electronics => 'blue',
        self::Clothing => 'purple',
        self::Apparel => 'grey',
    };
}
```

### in-array-mirrors-enum

`in_array($x, [literals])` whose literals mirror an existing enum's cases

```php
----------[ Bad ]----------

public function allowed(string $method): bool
{
    return in_array($method, ['card', 'ideal', 'paypal'], true);
}

----------[ Good ]----------

// in Shop\Payments\MethodWhitelist
public function allowedClean(string $method): bool
{
    return PaymentMethod::tryFrom($method) !== null;
}

enum PaymentMethod: string
{
    case Card = 'card';
    case Ideal = 'ideal';
    case PayPal = 'paypal';

    /**
     * The eligible set, sealed where the cases are. The call site used to re-derive it from an
     * or-chain, which is a copy of this answer that no new case can update.
     */
    public function isInstant(): bool
    {
        return match ($this) {
            self::Card, self::Ideal => true,
            self::PayPal => false,
        };
    }
}
```

### match-default-returns-null

`match` `default` that returns `null`/`false`/`[]` (or has no body) instead of throwing

```php
----------[ Bad ]----------

public function for(Product $product): ?string
{
    return match ($product->priority) {
        1 => 'urgent',
        2 => 'normal',
        3 => 'low',
        default => null,
    };
}

----------[ Good ]----------

// in Shop\Catalog\PriorityLabel
// The default arm throws a named exception, so an unhandled priority fails
// loudly instead of being swallowed into null.

public function strictFor(Product $product): string
{
    return match ($product->priority) {
        1 => 'urgent',
        2 => 'normal',
        3 => 'low',
        default => throw UnknownPriority::for($product->priority),
    };
}

// What the swallowed `default => null` becomes: the unhandled case, named. A priority nobody
// mapped is a gap in the mapping, and this is the only thing that says so out loud.

final class UnknownPriority extends RuntimeException
{
    public static function for(int $priority): self
    {
        return new self("No label is mapped for priority {$priority}.");
    }
}
```

### string-match-mirrors-enum

`match`/`switch` over string/int literals that mirror an existing backed enum's case values

```php
----------[ Bad ]----------

public function endpoint(string $method): string
{
    return match ($method) {
        'card' => 'https://pay.test/card',
        'ideal' => 'https://pay.test/ideal',
        'paypal' => 'https://pay.test/paypal',
        default => 'https://pay.test/fallback',
    };
}

----------[ Good ]----------

// in Shop\Payments\GatewayRouter
public function endpointClean(PaymentMethod $method): string
{
    return match ($method) {
        PaymentMethod::Card => 'https://pay.test/card',
        PaymentMethod::Ideal => 'https://pay.test/ideal',
        PaymentMethod::PayPal => 'https://pay.test/paypal',
    };
}

enum PaymentMethod: string
{
    case Card = 'card';
    case Ideal = 'ideal';
    case PayPal = 'paypal';

    /**
     * The eligible set, sealed where the cases are. The call site used to re-derive it from an
     * or-chain, which is a copy of this answer that no new case can update.
     */
    public function isInstant(): bool
    {
        return match ($this) {
            self::Card, self::Ideal => true,
            self::PayPal => false,
        };
    }
}
```

### unnamed-vocabulary-literal

A raw string in an argument the codebase elsewhere fills from a named vocabulary — `expect('{')` beside `expect(Token::COLON)`, where `Token::BRACE_OPEN` already names it

```php
----------[ Bad ]----------

public function heading(string $title): string
{
    $this->emit(ReceiptGlyph::RULE);

    return $this->emit('•') . $title;
}

----------[ Good ]----------

// in Shop\Labels\ReceiptPrinter
// The RESOLUTION — the glyph written under the name that already holds it.

public function separator(): string
{
    $this->emit(ReceiptGlyph::RULE);

    return $this->emit(ReceiptGlyph::BULLET);
}

// The characters a printed receipt is built from, and what the printer does with them. Named once so
// a layout reads as intent rather than punctuation — the vocabulary the printers below are meant to
// spell their rows with.

final class ReceiptGlyph
{
    public const string BULLET = '•';

    public const string RULE = '─';

    public const string COLUMN = '|';

    public const string ELLIPSIS = '…';

    /**
     * A horizontal rule $width glyphs wide.
     */
    public static function ruleOf(int $width): string
    {
        return str_repeat(self::RULE, $width);
    }

    /**
     * Is $char one of the glyphs a receipt is allowed to carry?
     */
    public static function isGlyph(string $char): bool
    {
        return in_array($char, [self::BULLET, self::RULE, self::COLUMN, self::ELLIPSIS], true);
    }
}
```
