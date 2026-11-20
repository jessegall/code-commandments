# Absence — ask whether it is there, directly and once — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### blank-string-default — in PHP

A blank string default stands for a missing required value

```php
----------[ Bad ]----------

public static function of(string $heading, string $strapline = ''): string
{
    if ($strapline === '') {
        return $heading;
    }

    return $heading . ' — ' . $strapline;
}

----------[ Good ]----------

public static function lined(string $heading, ?string $strapline = null): string
{
    if ($strapline === null) {
        return $heading;
    }

    return $heading . ' — ' . $strapline;
}
```

### blank-string-default — in TypeScript

A blank string default stands for a missing required value

```ts
----------[ Bad ]----------

export function labelInvoiceCode(name: string = ''): string {
    if (name === '') {
        return 'unassigned'
    }
    return name
}

----------[ Good ]----------

export function labelRequiredInvoiceCode(name: string): string {
    return name.toUpperCase()
}
```

### blank-string-default — in Python

A blank string default stands for a missing required value

```py
----------[ Bad ]----------

def book(self, parcel: str, tracking: str = "") -> str:
    if not tracking:
        tracking = self.api.issue(parcel)
    return self.api.book(parcel, tracking)

----------[ Good ]----------

def book_tracked(self, parcel: str, tracking: str | None = None) -> str:
    issued = tracking if tracking is not None else self.api.issue(parcel)
    return self.api.book(parcel, issued)
```

### blank-string-default — in C#

A blank string default stands for a missing required value

```cs
----------[ Bad ]----------

public static string Of(string heading, string strapline = "")
{
    if (strapline == "")
    {
        return heading;
    }

    return $"{heading} — {strapline}";
}

----------[ Good ]----------

public static string Lined(string heading, string? strapline = null)
{
    if (strapline is null)
    {
        return heading;
    }

    return $"{heading} — {strapline}";
}
```

### cancelled-coalesce — in PHP

A fallback compared against the same value it falls back to (`(name ?? '') !== ''`, `(name or "") != ""`): missing and empty end up in one branch without saying so

```php
----------[ Bad ]----------

public function hasSession(?string $sessionId): bool
{
    return ($sessionId ?? '') !== '';
}

----------[ Good ]----------

public function hasSessionStated(?string $sessionId): bool
{
    return $sessionId !== null && $sessionId !== '';
}
```

### cancelled-coalesce — in TypeScript

A fallback compared against the same value it falls back to (`(name ?? '') !== ''`, `(name or "") != ""`): missing and empty end up in one branch without saying so

```ts
----------[ Bad ]----------

export function hasNote(note: string | null): boolean {
    return (note ?? '') !== ''
}

----------[ Good ]----------

export function hasWrittenNote(note: string | null): boolean {
    return note !== null && note !== ''
}
```

### cancelled-coalesce — in Python

A fallback compared against the same value it falls back to (`(name ?? '') !== ''`, `(name or "") != ""`): missing and empty end up in one branch without saying so

```py
----------[ Bad ]----------

def has_coupon(basket) -> bool:
    return (basket.coupon if basket.coupon is not None else "") != ""

----------[ Good ]----------

def carries_coupon(basket) -> bool:
    return basket.coupon is not None
```

### cancelled-coalesce — in C#

A fallback compared against the same value it falls back to (`(name ?? '') !== ''`, `(name or "") != ""`): missing and empty end up in one branch without saying so

```cs
----------[ Bad ]----------

public static bool HasPostcode(string? postcode) => (postcode ?? "") != "";

----------[ Good ]----------

public static bool IsGiven(string? postcode) => postcode is not null && postcode != "";
```

### capability-probe-hub — in PHP

Several methods each ask the same field if it has a capability before using it

```php
----------[ Bad ]----------

// One installed shop plugin, and what it adds to the admin screens.

final class PluginSlot
{
    public function __construct(private readonly object $plugin) {}

    public function widgetCount(): int
    {
        return $this->plugin instanceof ContributesWidgets ? $this->plugin->widgetCount() : 0;
    }

    public function routeCount(): int
    {
        return $this->plugin instanceof ContributesRoutes ? $this->plugin->routeCount() : 0;
    }

    public function menuCount(): int
    {
        return $this->plugin instanceof ContributesMenus ? $this->plugin->menuCount() : 0;
    }
}

----------[ Good ]----------

// A shop plugin: it answers every contribution itself, none by default.

abstract class Plugin
{
    public function widgetCount(): int
    {
        return 0;
    }

    public function routeCount(): int
    {
        return 0;
    }

    public function menuCount(): int
    {
        return 0;
    }
}

// One installed shop plugin, told for what it adds to the admin screens.

final class PluginBay
{
    public function __construct(private readonly Plugin $plugin) {}

    public function additions(): int
    {
        return $this->plugin->widgetCount() + $this->plugin->routeCount() + $this->plugin->menuCount();
    }
}
```

### capability-probe-hub — in TypeScript

Several methods each ask the same field if it has a capability before using it

```ts
----------[ Bad ]----------

export class BillingProbe {
    constructor(private readonly provider: unknown) {}

    check(): boolean {
        if (this.provider instanceof BillingSource) { return this.provider.settled() }
        return false
    }

    rank(): number {
        if (this.provider instanceof BillingSource) { return this.provider.amount() }
        return 0
    }

    explain(): string {
        if (this.provider instanceof BillingSource) { return this.provider.describe() }
        return ''
    }
}

----------[ Good ]----------

export function receiveNotice(value: unknown): boolean {
    return value instanceof PendingNotice
}
```

### capability-probe-hub — in Python

Several methods each ask the same field if it has a capability before using it

```py
----------[ Bad ]----------

class BillingProbe:
    def __init__(self, provider: object) -> None:
        self.provider = provider

    def check(self) -> bool:
        if isinstance(self.provider, BillingSource):
            return self.provider.settled()
        return False

    def rank(self) -> int:
        if isinstance(self.provider, BillingSource):
            return self.provider.amount()
        return 0

    def explain(self) -> str:
        if isinstance(self.provider, BillingSource):
            return self.provider.describe()
        return ""

----------[ Good ]----------

def __init__(self, current: CounterTicket) -> None:
    self.current = current
```

### capability-probe-hub — in C#

Several methods each ask the same field if it has a capability before using it

```cs
----------[ Bad ]----------

public sealed class BillingProbe(object source)
{
    private readonly object backend = source;

    public bool Check()
    {
        if (this.backend is IBillingReady checkedValue) { return checkedValue.Settled(); }
        return false;
    }

    public int Rank()
    {
        if (this.backend is IBillingPriority ranked) { return ranked.Amount(); }
        return 0;
    }

    public string Explain()
    {
        if (this.backend is IBillingNote explained) { return explained.Describe(); }
        return "";
    }
}

----------[ Good ]----------

public bool Contains(string reference) => tickets.ContainsKey(reference);
```

### capability-probe-hub — in Go

Several methods each ask the same field if it has a capability before using it

```go
----------[ Bad ]----------

type BillingProbe struct{ provider any }

----------[ Good ]----------

type receiptReader struct {
	source any
}
```

### coalesced-loop-subject — in PHP

The loop header replaces a missing collection with an empty one

```php
----------[ Bad ]----------

public function fanOut(string $carrier, array $manifest): void
{
    foreach ($manifest[$carrier] ?? [] as $parcel) {
        $this->queued[$carrier][] = $parcel;
    }
}

----------[ Good ]----------

public function fanOutGuarded(string $carrier, array $manifest): void
{
    if (! isset($manifest[$carrier])) {
        return;
    }

    foreach ($manifest[$carrier] as $parcel) {
        $this->queued[$carrier][] = $parcel;
    }
}
```

### coalesced-loop-subject — in TypeScript

The loop header replaces a missing collection with an empty one

```ts
----------[ Bad ]----------

export function sendInvoiceCodeLabels(values: readonly string[] | undefined, send: (value: string) => void): void {
    for (const value of values ?? []) {
        send(value)
    }
}

----------[ Good ]----------

export function countNotices(notices?: readonly Notice[]): number {
    if (!notices) {
        return 0
    }
    let total = 0
    for (const item of notices) {
        total += item.count
    }
    return total
}
```

### coalesced-loop-subject — in Python

The loop header replaces a missing collection with an empty one

```py
----------[ Bad ]----------

def send(self, to: str, body: str, files: list | None = None) -> None:
    message = self.outbox.compose(to, body)
    for path in files or []:
        message.attach(path)
    self.outbox.deliver(message)

----------[ Good ]----------

def send_with(self, to: str, body: str, files: tuple[str, ...] = ()) -> None:
    message = self.outbox.compose(to, body)
    for path in files:
        message.attach(path)
    self.outbox.deliver(message)
```

### coalesced-loop-subject — in C#

The loop header replaces a missing collection with an empty one

```cs
----------[ Bad ]----------

public static List<string> Lines(List<string>? skus)
{
    var lines = new List<string>();

    foreach (var sku in skus ?? [])
    {
        lines.Add($"[ ] {sku}");
    }

    return lines;
}

----------[ Good ]----------

public static List<string> GuardedLines(List<string>? skus)
{
    if (skus is null)
    {
        return [];
    }

    var lines = new List<string>();

    foreach (var sku in skus)
    {
        lines.Add($"[ ] {sku}");
    }

    return lines;
}
```

### conditional-array-spread — in PHP

A spread branch supplies an empty collection as a placeholder

```php
----------[ Bad ]----------

public function toArray(): array
{
    return [
        'key' => $this->key,
        ...($this->label !== null ? ['label' => $this->label] : []),
        ...($this->icon === null ? [] : ['icon' => $this->icon]),
    ];
}

----------[ Good ]----------

// in Shop\Domain\Descriptor
public function toArray(): array
{
    return Payload::of(key: $this->key, label: $this->label, icon: $this->icon);
}

// in Shop\Domain\Payload
public static function of(mixed ...$values): array
{
    $payload = [];

    foreach ($values as $name => $value) {
        if ($value !== null) {
            $payload[$name] = $value;
        }
    }

    return $payload;
}
```

### conditional-array-spread — in TypeScript

A spread branch supplies an empty collection as a placeholder

```ts
----------[ Bad ]----------

export function listInvoiceCode(published: boolean): string[] {
    return [...(published ? ['PAID'] : []), 'draft']
}

----------[ Good ]----------

export function combineNotices(left: readonly Notice[], right: readonly Notice[]): Notice[] {
    return [...left, ...right]
}
```

### conditional-array-spread — in Python

A spread branch supplies an empty collection as a placeholder

```py
----------[ Good ]----------

def picked_up(ticket: CounterTicket, show_title: bool) -> list:
    parts = [ticket.sequence]
    if show_title:
        parts.append(ticket.title)
    return parts
```

### de-nulled-finder — in PHP

Every caller takes a nullable result as present

```php
----------[ Bad ]----------

public function byBarcode(string $barcode): ?Product
{
    return Product::query()->where('barcode', $barcode)->first();
}

----------[ Good ]----------

// in Shop\Catalog\ProductFinder
// Resolve-or-throw: a scanned barcode must exist, so the absence is decided
// once at the source and the return type tells the truth.

public function requireByBarcode(string $barcode): Product
{
    return Product::query()->where('barcode', $barcode)->first()
        ?? throw ProductNotFound::forBarcode($barcode);
}

// The absence the finder used to hand back as null, decided once and named. Every caller that
// de-nulled the `?Product` is now spared the question.

final class ProductNotFound extends RuntimeException
{
    public static function forBarcode(string $barcode): self
    {
        return new self("No product is registered under barcode {$barcode}.");
    }
}
```

### de-nulled-finder — in TypeScript

Every caller takes a nullable result as present

```ts
----------[ Bad ]----------

function findInvoiceCode(bag: Record<string, Result>, key: string): Result | undefined {
    return bag[key]
}

----------[ Good ]----------

export function noticeCount(title: string): number {
    const found = optionalNotice(title)
    return found ? found.count : 0
}
```

### de-nulled-finder — in Python

Every caller takes a nullable result as present

```py
----------[ Bad ]----------

def find(self, reference: str) -> Crate | None:
    return self.kept.get(reference)

----------[ Good ]----------

# in crate_registry.py
def crate(self, reference: str) -> Crate:
    found = self.kept.get(reference)
    if found is None:
        raise CrateMissing(reference)

    return found

# in crate_labels.py
def labelled(self, reference: str) -> str:
    return self.registry.crate(reference).reference
```

### de-nulled-finder — in C#

Every caller takes a nullable result as present

```cs
----------[ Bad ]----------

public CouponCode? Find(string code) => byCode.GetValueOrDefault(code);

----------[ Good ]----------

public CouponCode Get(string code) => byCode.TryGetValue(code, out var coupon) ? coupon : throw new KeyNotFoundException(code);
```

### manufactured-fake-fill — in PHP

An empty scalar fills a slot that expects a meaningful value

```php
----------[ Bad ]----------

public function import(array $rows): void
{
    foreach ($rows as $row) {
        $customer = $this->findCustomer($row['email'] ?? '');

        // changed from update() to direct assignment in v2
        if ($customer !== null) {
            $customer->imported = true;
            $customer->save();
        }
    }
}

----------[ Good ]----------

// in Shop\Legacy\LegacyOrderImporter
// The FIX: a row with no email is an absence at the SOURCE, so the boundary names the failure and
// throws. The `?? ''` version looked up "the customer whose email is the empty string" and carried
// that fake all the way to the import — the throw stops the row here, where the truth is known.

public function importStrictly(array $rows): void
{
    foreach ($rows as $row) {
        $email = $row['email'] ?? throw new MissingImportEmail();

        $this->findCustomer($email)?->markImported();
    }
}

// in Shop\Models\Customer
public function markImported(): void
{
    $this->imported = true;
    $this->save();
}
```

### manufactured-fake-fill — in TypeScript

An empty scalar fills a slot that expects a meaningful value

```ts
----------[ Bad ]----------

export function publishOptionalInvoiceCode(label: string | undefined, write: (label: string) => void): void {
    publishInvoiceCode(label ?? '', write)
}

----------[ Good ]----------

export function sendRequiredInvoiceCode(label: string | undefined, write: (label: string) => void): void {
    if (label !== undefined) {
        publishInvoiceCode(label, write)
    }
}
```

### manufactured-fake-fill — in Python

An empty scalar fills a slot that expects a meaningful value

```py
----------[ Good ]----------

def assign_title(self, title: str) -> None:
    self.current.title = title
```

### masked-invariant — in PHP

Masking an optional field behind a literal hides a value the design requires

```php
----------[ Bad ]----------

public function accepts(string $sku): bool
{
    return $this->batch?->permits($sku) ?? false;
}

----------[ Good ]----------

// The FIX for {@see GradeSelector}: the invariant is made CERTAIN instead of masked — the batch is
// held non-nullable (a grading pass without one cannot be constructed), so the read is a plain
// `$this->batch->permits($sku)` with no `?->` and no fake `?? false` answering an impossible state.

final class OpenGradeSelector
{
    public function __construct(private readonly ActiveBatch $batch) {}

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    public function passing(array $skus): array
    {
        return array_values(array_filter($skus, fn (string $sku) => $this->batch->permits($sku)));
    }

    public function accepts(string $sku): bool
    {
        return $this->batch->permits($sku);
    }
}
```

### masked-invariant — in TypeScript

Masking an optional field behind a literal hides a value the design requires

```ts
----------[ Bad ]----------

draftValue(): string {
    return this.draft?.memo ?? ''
}

----------[ Good ]----------

title(): string {
    return this.current?.title ?? 'Pending'
}
```

### masked-invariant — in Python

Masking an optional field behind a literal hides a value the design requires

```py
----------[ Bad ]----------

def reduced(self, sku: str) -> bool:
    return self.markdown.applies_to(sku) if self.markdown else False

----------[ Good ]----------

def run(self, markdown: Markdown, skus: list[str]) -> list[str]:
    return [sku for sku in skus if markdown.applies_to(sku)]
```

### masked-invariant — in C#

Masking an optional field behind a literal hides a value the design requires

```cs
----------[ Bad ]----------

public string DraftValue()
{
    return this.Draft?.Memo ?? "";
}

----------[ Good ]----------

public DateTime? EstimatedDispatch()
{
    return active?.DispatchAt;
}
```

### nullable-callback — in PHP

A callback is declared optional, checked for absence, and then invoked

```php
----------[ Bad ]----------

public function run(Closure $work, Closure | null $onRetry = null): mixed
{
    while (true) {
        $this->attempts++;

        try {
            return $work();
        } catch (\Throwable $e) {
            if ($onRetry) {
                $onRetry($this->attempts);
            }

            if ($this->attempts >= 3) {
                throw $e;
            }
        }
    }
}

----------[ Good ]----------

public function runWith(Closure $work, Invokable $onRetry = new NoOp): mixed
{
    while (true) {
        $this->attempts++;

        try {
            return $work();
        } catch (\Throwable $e) {
            $onRetry($this->attempts);

            if ($this->attempts >= 3) {
                throw $e;
            }
        }
    }
}
```

### nullable-callback — in TypeScript

A callback is declared optional, checked for absence, and then invoked

```ts
----------[ Bad ]----------

announce(notify?: () => void): void {
    notify?.()
}

----------[ Good ]----------

export function sendNotice(item: Notice, delivered: (count: number) => void = ignoreDelivery): void {
    delivered(item.count)
}
```

### nullable-callback — in Python

A callback is declared optional, checked for absence, and then invoked

```py
----------[ Bad ]----------

def close_till(till, after: Callable[[], None] | None = None) -> float:
    total = till.count()
    if after:
        after()
    return total

----------[ Good ]----------

def close_till_then(till, after: Callable[[], None] = noop) -> float:
    total = till.count()
    after()
    return total
```

### nullable-callback — in C#

A callback is declared optional, checked for absence, and then invoked

```cs
----------[ Bad ]----------

public void Announce(Action? notify = null)
{
    if (notify != null)
    {
        notify();
    }
}

----------[ Good ]----------

public void Publish(string reference, Action<DispatchTicket> notify)
{
    notify(tickets[reference]);
}
```

### nullable-registry-lookup — in PHP

A keyed store returns absence on a miss

```php
----------[ Bad ]----------

public function get(string $key): ?object
{
    return $this->channels[$key] ?? null;
}

----------[ Good ]----------

// in Shop\Support\NotificationChannels
// The FIX: the store resolves-or-throws. A missing key is a named failure at the source
// (`UnknownChannel::forKey($key)`), never a `null` every caller has to re-decide about.

public function resolve(string $key): object
{
    return $this->channels[$key] ?? throw UnknownChannel::forKey($key);
}

// The failure the nullable lookup used to hand back as null. Naming it is what lets the store
// resolve-or-throw, so no caller re-decides what a missing channel means.

final class UnknownChannel extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No notification channel is registered under {$key}.");
    }
}
```

### nullable-registry-lookup — in TypeScript

A keyed store returns absence on a miss

```ts
----------[ Bad ]----------

find(key: string): Invoice | undefined {
    return this.records.get(key)
}

----------[ Good ]----------

export function getNotice(index: ReadonlyMap<string, Notice>, key: string): Notice | undefined {
    return index.get(key)
}
```

### nullable-registry-lookup — in Python

A keyed store returns absence on a miss

```py
----------[ Bad ]----------

def get(self, till: str) -> ReceiptPrinter | None:
    return self._printers.get(till)

----------[ Good ]----------

# in printer_pool.py
def get(self, till: str) -> ReceiptPrinter:
    try:
        return self._printers[till]
    except KeyError as missing:
        raise UnknownTill.for_name(till) from missing

# in printer_pool.py
def __contains__(self, till: str) -> bool:
    return till in self._printers
```

### nullable-registry-lookup — in C#

A keyed store returns absence on a miss

```cs
----------[ Bad ]----------

public Invoice? Find(string key)
{
    return this.records.GetValueOrDefault(key);
}

----------[ Good ]----------

public DispatchTicket Require(string reference) => tickets[reference];
```

### phantom-nullable — in PHP

Every dereference assumes the declared optional value is present

```php
----------[ Bad ]----------

// An order entering fulfillment. `$shipTo` is typed `?ShippingAddress`, but staging copies it onto the
// pick list, which copies it onto the packing slip, onto the label, onto the manifest — five records
// across five files — where it is finally read as present, and no step ever guards the null.

final class Order
{
    public function __construct(
        public readonly ?ShippingAddress $shipTo,
        public readonly string $reference,
    ) {}

    public function stage(PickList $pickList): void
    {
        $pickList->deliverTo = $this->shipTo;
    }
}

----------[ Good ]----------

// The FIX for {@see Order}: `$shipTo` is typed `ShippingAddress` — NOT nullable. Every reader
// already assumed it, so the type now says so and construction fails hard on a real miss instead
// of carrying a null nobody ever guards.

final class ConfirmedOrder
{
    public function __construct(
        public readonly ShippingAddress $shipTo,
        public readonly string $reference,
    ) {}

    public function routingLine(): string
    {
        return $this->reference . ' @ ' . $this->shipTo->postalCode();
    }
}
```

### phantom-nullable — in Python

Every dereference assumes the declared optional value is present

```py
----------[ Bad ]----------

@dataclass(frozen=True)
class PriceTag:
    cents: int
    promotion: str | None = None

    def banner(self) -> str:
        return self.promotion.upper()

----------[ Good ]----------

@dataclass(frozen=True)
class PromotedTag:
    cents: int
    promotion: str

    def banner(self) -> str:
        return self.promotion.upper()
```

### phantom-nullable — in C#

Every dereference assumes the declared optional value is present

```cs
----------[ Bad ]----------

public sealed class BillingState
{
    public Invoice? Draft { get; private set; }
    public Invoice? Active { get; init; }
    private readonly Dictionary<string, Invoice> records = new();

    public void Prepare(Invoice supplied)
    {
        this.Draft = supplied;
    }

    public string DraftValue()
    {
        return this.Draft?.Memo ?? "";
    }

    public string ActiveValue()
    {
        return this.Active.Memo;
    }

    public void Announce(Action? notify = null)
    {
        if (notify != null)
        {
            notify();
        }
    }

    public Invoice? Find(string key)
    {
        return this.records.GetValueOrDefault(key);
    }

    public JsonDocument Read(string written)
    {
        return JsonDocument.Parse(written);
    }

    public Invoice Parse(string written)
    {
        return JsonSerializer.Deserialize<Invoice>(written) ?? throw new FormatException();
    }
}

----------[ Good ]----------

public DateTime? EstimatedDispatch()
{
    return active?.DispatchAt;
}
```

### phantom-nullable — in Go

Every dereference assumes the declared optional value is present

```go
----------[ Bad ]----------

BillingState struct {
	Active *BillingResult
}

----------[ Good ]----------

retainedReceipt struct {
	active *VerifiedReceipt
}
```

### placeholder-filled-data — in PHP

A blank string fills a required string field in a data type

```php
----------[ Bad ]----------

public function activate(string $slug): WorkflowRowData
{
    return new WorkflowRowData(slug: $slug, name: $slug, trigger: null, active: true, updatedAt: '');
}

----------[ Good ]----------

// The FIX for {@see ActivateWorkflow}: the required `updatedAt` slot is handed the REAL value — the
// command holds the clock that owns the timestamp and reads it, instead of filling the promise with
// `''` to satisfy the signature.

final class StampedActivateWorkflow
{
    public function __construct(private readonly WorkflowClock $clock) {}

    public function activate(string $slug): WorkflowRowData
    {
        return new WorkflowRowData(
            slug: $slug,
            name: $slug,
            trigger: null,
            active: true,
            updatedAt: $this->clock->stamp(),
        );
    }
}

// The source of the timestamp the fixed activation asks for.

final class WorkflowClock
{
    public function stamp(): string
    {
        return '2024-01-01T00:00:00Z';
    }
}
```

### placeholder-filled-data — in TypeScript

A blank string fills a required string field in a data type

```ts
----------[ Bad ]----------

export function newInvoiceCode(): InvoiceCode {
    return new InvoiceCode('')
}

----------[ Good ]----------

export function namedInvoiceCode(value: string): InvoiceCode {
    return new InvoiceCode(value)
}
```

### placeholder-filled-data — in Python

A blank string fills a required string field in a data type

```py
----------[ Bad ]----------

def card_for(order) -> OrderCard:
    return OrderCard(title=order.number, body="")

----------[ Good ]----------

# in order_cards.py
@dataclass(frozen=True)
class OrderHeading:
    title: str

# in order_cards.py
def heading_for(order) -> OrderHeading:
    return OrderHeading(title=order.number)
```

### placeholder-filled-data — in C#

A blank string fills a required string field in a data type

```cs
----------[ Bad ]----------

public static Payout Queued(string sellerId, int cents) => new Payout(sellerId, cents, "");

----------[ Good ]----------

// in Payouts.cs
public sealed record PendingPayout(string SellerId, int Cents);

// in Payouts.cs
public static PendingPayout Queue(string sellerId, int cents) => new PendingPayout(sellerId, cents);
```

### raw-decoded-array-return — in PHP

A function returns decoded data before checking and giving it a type

```php
----------[ Bad ]----------

public function rates(string $base, array $symbols): array
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return json_decode($this->http->get("https://fx.test/latest?{$query}"), true);
}

----------[ Good ]----------

// in Shop\Integrations\ExchangeRateClient
public function ratesTyped(string $base, array $symbols): RateTable
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return RateTable::from(json_decode($this->http->get("https://fx.test/latest?{$query}"), true));
}

// The shape the wire actually has, declared. A decoded response handed back raw asks every caller
// to know the payload's keys; this states them once, at the boundary that read them.

final class RateTable
{
    /**
     * @param  array<string, float>  $rates
     */
    public function __construct(
        public readonly string $base,
        public readonly array $rates,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            base: (string) $payload['base'],
            rates: $payload['rates'],
        );
    }
}
```

### raw-decoded-array-return — in TypeScript

A function returns decoded data before checking and giving it a type

```ts
----------[ Bad ]----------

read(written: string): unknown {
    return JSON.parse(written)
}

----------[ Good ]----------

export function restoreNotice(text: string): Notice {
    const found: unknown = JSON.parse(text)
    if (typeof found === 'string') {
        return notice(found, 1)
    }
    throw new TypeError('Expected a notice title')
}
```

### raw-decoded-array-return — in Python

A function returns decoded data before checking and giving it a type

```py
----------[ Good ]----------

def latest_ticket(payload: str) -> CounterTicket:
    title, sequence = json.loads(payload)
    if not title:
        raise ManifestUnreadable.missing_ticket()
    return CounterTicket(title, sequence)
```

### raw-decoded-array-return — in C#

A function returns decoded data before checking and giving it a type

```cs
----------[ Bad ]----------

public JsonDocument Read(string written)
{
    return JsonDocument.Parse(written);
}

----------[ Good ]----------

public Invoice Parse(string written)
{
    return JsonSerializer.Deserialize<Invoice>(written) ?? throw new FormatException();
}
```

### raw-decoded-array-return — in Go

A function returns decoded data before checking and giving it a type

```go
----------[ Bad ]----------

func readBilling(invoices [][]byte, record func(int)) (any, error) {
	for number, invoice := range invoices {
		if len(invoice) == 0 {
			continue
		}
		var item any
		if err := json.Unmarshal(invoice, &item); err != nil {
			return nil, err
		}
		record(number)
		return item, nil
	}
	return nil, nil
}

----------[ Good ]----------

func decodeBilling(data []byte) (BillingResult, error) {
	var result BillingResult
	err := json.Unmarshal(data, &result)
	return result, err
}
```
