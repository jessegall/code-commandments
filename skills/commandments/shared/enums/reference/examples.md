# Enums: give a closed set one source — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### const-class-enum — in PHP

A class of constants defines a closed set of cases

```php
----------[ Bad ]----------

// VAT rates kept as floating-point constants instead of a typed TaxBand enum.

final class TaxBands
{
    /**
     * The default rate applied to most goods.
     */
    const STANDARD = 0.21;

    /**
     * Food, books, and other reduced-rate categories.
     */
    const REDUCED = 0.09;

    /**
     * Exports and exempt supplies.
     */
    const ZERO = 0.0;
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

### const-class-enum — in TypeScript

A class of constants defines a closed set of cases

```ts
----------[ Bad ]----------

export class InvoiceBand {
    static readonly Standard = 20
    static readonly Express = 64
}

----------[ Good ]----------

export class NoticePrinter {
    static readonly paperWidth = 40

    constructor(private readonly emit: (line: string) => void) {}

    post(kind: DeskNoticeKind, desk: number): void {
        this.emit(`Desk ${desk}: ${kind}`)
    }
}
```

### const-class-enum — in C#

A class of constants defines a closed set of cases

```cs
----------[ Bad ]----------

public static class PaymentState
{
    public const string Pending = "pending";
    public const string Captured = "captured";
    public const string Refunded = "refunded";
}

----------[ Good ]----------

// in PaymentStates.cs
public enum PaymentStatus
{
    Pending,
    Captured,
    Refunded,
}

// in PaymentStates.cs
public static class PaymentStatusRules
{
    public static bool IsSettled(this PaymentStatus status) => status switch
    {
        PaymentStatus.Pending => false,
        PaymentStatus.Captured => true,
        PaymentStatus.Refunded => true,
    };
}
```

### enum-case-or-chain — in PHP

An or-chain asks about several cases of one enum

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

### enum-case-or-chain — in TypeScript

An or-chain asks about several cases of one enum

```ts
----------[ Bad ]----------

export function active(stage: InvoiceStage): boolean {
    return stage === InvoiceStage.Unposted || stage === InvoiceStage.Settled
}

----------[ Good ]----------

export function belongsToActiveStages(stage: InvoiceStage): boolean {
    return activeStages.has(stage)
}
```

### enum-case-or-chain — in Python

An or-chain asks about several cases of one enum

```py
----------[ Bad ]----------

def needs_reminder(invoice) -> bool:
    return invoice.status == InvoiceStatus.OPEN or invoice.status == InvoiceStatus.OVERDUE

----------[ Good ]----------

def owes_money(invoice) -> bool:
    return invoice.status.is_unpaid
```

### enum-case-or-chain — in C#

An or-chain asks about several cases of one enum

```cs
----------[ Bad ]----------

public static string Note(DeliveryStage stage)
{
    if (stage == DeliveryStage.Shipped || stage == DeliveryStage.Delivered)
    {
        return "on its way";
    }

    return "still here";
}

----------[ Good ]----------

// in Fulfilment.cs
public static class DeliveryStageRules
{
    public static bool HasLeftTheWarehouse(this DeliveryStage stage) => stage switch
    {
        DeliveryStage.Shipped or DeliveryStage.Delivered => true,
        _ => false,
    };
}

// in Fulfilment.cs
public static string Noted(DeliveryStage stage) => stage.HasLeftTheWarehouse() ? "on its way" : "still here";
```

### in-array-mirrors-enum — in PHP

A literal membership check repeats declared enum values

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

### in-array-mirrors-enum — in TypeScript

A literal membership check repeats declared enum values

```ts
----------[ Bad ]----------

export function accepted(value: string): boolean {
    return ['unposted', 'settled'].includes(value)
}

----------[ Good ]----------

export function acceptsStage(value: InvoiceStage): boolean {
    return activeStages.has(value)
}
```

### in-array-mirrors-enum — in Python

A literal membership check repeats declared enum values

```py
----------[ Good ]----------

def takes_queue(status: DeskStatus) -> bool:
    return status is DeskStatus.OPEN
```

### in-array-mirrors-enum — in C#

A literal membership check repeats declared enum values

```cs
----------[ Bad ]----------

public static bool Settles(PaymentCallback callback) => new[] { "paid", "shipped" }.Contains(callback.Status);

----------[ Good ]----------

// in PaymentWebhooks.cs
public static class OrderStatusRules
{
    public static bool IsSettled(this OrderStatus status) => status switch
    {
        OrderStatus.Paid or OrderStatus.Shipped => true,
        _ => false,
    };
}

// in PaymentWebhooks.cs
public static bool Settled(PaymentCallback callback) => Enum.TryParse(callback.Status, ignoreCase: true, out OrderStatus status) && status.IsSettled();
```

### side-table-trait — in PHP

A separate list repeats which cases have a trait

```php
----------[ Bad ]----------

// in app/Shipping/CarrierService.php
namespace Shop\Shipping;

/**
 * Which services need a signature is kept as a list of the cases it is true of, beside the cases
 * themselves. A service added tomorrow is silently not one of them, and has nowhere to say it is.
 */
enum CarrierService: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Insured = 'insured';
    case Pallet = 'pallet';

    private const SIGNED_FOR = [self::Insured, self::Pallet];

    public function needsSignature(): bool
    {
        return in_array($this, self::SIGNED_FOR, true);
    }
}

----------[ Good ]----------

// in app/Shipping/ReturnWindow.php
namespace Shop\Shipping;

/**
 * The same trait, answered by the case instead of listed beside it: a window added tomorrow cannot
 * be written without saying whether it is extended, because the match has no case to fall through to.
 */
enum ReturnWindow: string
{
    case Fortnight = 'fortnight';
    case Month = 'month';
    case Quarter = 'quarter';

    public function isExtended(): bool
    {
        return match ($this) {
            self::Fortnight => false,
            self::Month, self::Quarter => true,
        };
    }
}
```

### side-table-trait — in TypeScript

A separate list repeats which cases have a trait

```ts
----------[ Bad ]----------

// in client/tag-speech.ts
type SpeechTag = { name: string; command: string }

const TAGS: SpeechTag[] = [
  { name: 'reply', command: 'message reply' },
  { name: 'log', command: 'work log' },
  { name: 'todo', command: 'todo create' },
]

const QUIET = new Set(['reply', 'log'])

export function saidInChat(name: string): boolean {
  return !QUIET.has(name)
}

----------[ Good ]----------

// in client/tag-speech-fixed.ts
type SpokenTag = { name: string; command: string; saidInChat: boolean }

const TAGS: SpokenTag[] = [
  { name: 'reply', command: 'message reply', saidInChat: false },
  { name: 'log', command: 'work log', saidInChat: false },
  { name: 'todo', command: 'todo create', saidInChat: true },
]

const QUIET = new Set(TAGS.filter((tag) => !tag.saidInChat).map((tag) => tag.name))

export function saidInChat(tag: SpokenTag): boolean {
  return tag.saidInChat
}
```

### side-table-trait — in Python

A separate list repeats which cases have a trait

```py
----------[ Bad ]----------

# in shop/tag_speech.py
# Which tags keep their words out of the chat is kept as a set of the ones it is true of, beside the
# tags themselves. A tag added tomorrow is silently spoken aloud, and has nowhere to say otherwise.
from dataclasses import dataclass

@dataclass(frozen=True)
class Tag:
    name: str
    runs: str
    hint: str

TAGS = (
    Tag("reply", "message reply", "answer the message"),
    Tag("log", "work log", "write what moved"),
    Tag("await", "work await", "say what is waited on"),
    Tag("todo", "todo create", "file it"),
)

QUIET = {"reply", "log", "await"}

def said_in_chat(name: str) -> bool:
    return name not in QUIET

----------[ Good ]----------

# in shop/tag_speech_fixed.py
# The repair: the trait is a field each tag answers for itself, so a tag written tomorrow has to say
# whether it is spoken aloud, and the set that reads it is derived from the tags rather than kept beside
# them. There is no second place left to forget.
from dataclasses import dataclass

@dataclass(frozen=True)
class SpokenTag:
    name: str
    runs: str
    hint: str
    said_in_chat: bool = True

SPOKEN_TAGS = (
    SpokenTag("reply", "message reply", "answer the message", said_in_chat=False),
    SpokenTag("log", "work log", "write what moved", said_in_chat=False),
    SpokenTag("await", "work await", "say what is waited on", said_in_chat=False),
    SpokenTag("todo", "todo create", "file it"),
)

def said_aloud(tag: SpokenTag) -> bool:
    return tag.said_in_chat
```

### side-table-trait — in C#

A separate list repeats which cases have a trait

```cs
----------[ Bad ]----------

// in Shop/Tags/TagSpeech.cs
using System.Collections.Generic;

namespace Shop.Tags;

public sealed record SpeechTag(string Name, string Command);

public static class TagSpeech
{
    public static readonly SpeechTag[] All = {
        new SpeechTag("reply", "message reply"),
        new SpeechTag("log", "work log"),
        new SpeechTag("todo", "todo create")
    };

    private static readonly HashSet<string> Quiet = new() { "reply", "log" };

    public static bool SaidInChat(string name) => !Quiet.Contains(name);
}

----------[ Good ]----------

// in Shop/Tags/TagSpeechFixed.cs
using System.Collections.Generic;

namespace Shop.Tags;

public sealed record SpokenTag(string Name, string Command, bool SaidInChat);

public static class TagSpeechFixed
{
    public static readonly SpokenTag[] All = {
        new SpokenTag("reply", "message reply", false),
        new SpokenTag("log", "work log", false),
        new SpokenTag("todo", "todo create", true)
    };

    public static bool SaidInChat(SpokenTag tag) => tag.SaidInChat;
}
```

### string-match-mirrors-enum — in PHP

A switch repeats literal values already declared as cases

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

### string-match-mirrors-enum — in TypeScript

A switch repeats literal values already declared as cases

```ts
----------[ Bad ]----------

export function caption(value: string): string {
    switch (value) {
        case 'unposted': return 'first stage'
        case 'settled': return 'second stage'
        case 'voided': return 'final stage'
        default: return 'unrecognized'
    }
}

----------[ Good ]----------

export function captionStage(stage: InvoiceStage): string {
    switch (stage) {
        case InvoiceStage.Unposted: return 'first stage'
        case InvoiceStage.Settled: return 'second stage'
        case InvoiceStage.Voided: return 'final stage'
    }
}
```

### string-match-mirrors-enum — in Python

A switch repeats literal values already declared as cases

```py
----------[ Bad ]----------

def slot_fee(slot: str) -> int:
    match slot:
        case "morning":
            return 0
        case "evening":
            return 5

----------[ Good ]----------

def slot_fee_for(slot: Slot) -> int:
    return slot.fee
```

### type-switch — in PHP

Dispatch branches test the same value against several concrete types

```php
----------[ Bad ]----------

public function price(Freightable $freight): int
{
    if ($freight instanceof ExpressFreight) {
        return $freight->weightGrams() * 12 + 500;
    } elseif ($freight instanceof PalletFreight) {
        return $freight->pallets() * 4_000;
    }

    return $freight->weightGrams() * 3;
}

----------[ Good ]----------

// in Shop\Shipping\ConsignmentFee
// The FIX: one method on the shared interface, implemented per freight type. Each kind answers
// for itself, so a new kind needs no edit here at all.

public function priceTold(PricedFreight $freight): int
{
    return $freight->priceCents();
}

// What the caller actually wants to know. Each kind of freight answers it for itself, so pricing a
// new kind adds a class rather than a branch.

interface PricedFreight
{
    public function priceCents(): int;
}

// in Shop\Shipping\ExpressFreight
public function priceCents(): int
{
    return $this->weightGrams() * 12 + 500;
}

// in Shop\Shipping\PalletFreight
public function priceCents(): int
{
    return $this->pallets() * 4_000;
}
```

### type-switch — in TypeScript

Dispatch branches test the same value against several concrete types

```ts
----------[ Bad ]----------

export function measure(value: StandardInvoice | ExpressInvoice): number {
    let reading: number
    if (value instanceof StandardInvoice) {
        reading = value.amount + 1
    } else if (value instanceof ExpressInvoice) {
        reading = value.amount * 2
    } else {
        reading = 0
    }
    return reading
}

----------[ Good ]----------

export function serveDesk(notice: { show(): string }): string {
    return notice.show()
}
```

### type-switch — in Python

Dispatch branches test the same value against several concrete types

```py
----------[ Bad ]----------

def quote(parcel: Parcel) -> int:
    if isinstance(parcel, Letter):
        return 120
    elif isinstance(parcel, Pallet):
        return 4000 + parcel.weight_grams // 100
    return 500

----------[ Good ]----------

def quote_told(parcel: Parcel) -> int:
    return parcel.rate_cents()
```

### type-switch — in C#

Dispatch branches test the same value against several concrete types

```cs
----------[ Bad ]----------

public static string Label(Promotion promotion) => promotion switch
{
    PercentOff p => $"{p.Percent}% off",
    AmountOff a => $"{a.Amount / 100m:C} off",
    _ => "",
};

----------[ Good ]----------

// in Promotions.cs
// A promotion a basket can carry, and what it takes off the price.
public abstract class Promotion
{
    public abstract int Discount(int cents);

    public abstract string Label();
}

// in Promotions.cs
public override string Label() => $"{Percent}% off";

// in Promotions.cs
public override string Label() => $"{Amount / 100m:C} off";

// in Promotions.cs
public static string Describe(Promotion promotion) => promotion.Label();
```
