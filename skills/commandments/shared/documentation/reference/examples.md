# Documentation: explain the code as it is — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### archaeology-comment — in PHP

A comment narrates the history of the code

```php
----------[ Bad ]----------

public function handle(array $event): void
{
    // formerly lived inline in the StripeController; was extracted here
    $type = $event['type'];

    $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
}

----------[ Good ]----------

public function handleRefund(array $event): void
{
    // a refund carries no id of its own, so the charge reference identifies the row
    $this->record('refund', $this->reference($event));
}
```

### archaeology-comment · negative-space-comment · restated-comment — in TypeScript

_archaeology-comment_ — A comment narrates the history of the code

_negative-space-comment_ — A comment defends the code against an assumption the reader has no reason to make

_restated-comment_ — A comment repeats the words of the statement it documents

```ts
----------[ Bad ]----------

export function drawerBalance(drawer: { balance: number }): number {
    // Previously this calculation used a cached value.
    const amount = drawer.balance
    // The currency is deliberately not rounded here.
    const calculated = Math.abs(amount)
    // Return calculated amount.
    return calculated + amount
}

----------[ Good ]----------

export function drawerForDisplay(amount: number): number {
    return Math.abs(amount)
}
```

### archaeology-comment — in Python

A comment narrates the history of the code

```py
----------[ Bad ]----------

# formerly lived in the checkout module, before invoices had their own
def next_number(last: int, prefix: str) -> str:
    return f"{prefix}-{last + 1:06d}"

----------[ Good ]----------

def next_invoice_number(last: int, prefix: str) -> str:
    # six digits, because the accounting export pads to a fixed width
    return f"{prefix}-{last + 1:06d}"
```

### archaeology-comment — in C#

A comment narrates the history of the code

```cs
----------[ Bad ]----------

// formerly lived in the checkout service, and was extracted here
public static int Days(bool member) => member ? 60 : 30;

----------[ Good ]----------

/// <summary>Members get longer to decide, since they return far less often.</summary>
public static int DaysFor(bool member) => member ? 60 : 30;
```

### archaeology-comment · negative-space-comment · restated-comment — in Go

_archaeology-comment_ — A comment narrates the history of the code

_negative-space-comment_ — A comment defends the code against an assumption the reader has no reason to make

_restated-comment_ — A comment repeats the words of the statement it documents

```go
----------[ Bad ]----------

func drawerBalance(amount int) int {
	// Previously this calculation used a cached value.
	calculated := amount + 1
	// The adjustment is deliberately not rounded here.
	adjusted := calculated * 2
	// Return adjusted amount.
	return adjusted + amount
}

----------[ Good ]----------

func drawerForDisplay(amount int) int {
	return amount + 1
}
```

### bloated-docblock — in PHP

A type has several paragraphs of description

```php
----------[ Bad ]----------

// Builds the monthly sales report. It pulls orders, groups them by customer and
// region, applies the tax tables, and renders a spreadsheet that finance imports
// by hand every month.
// The grouping logic is shared with the dashboard widgets, so any change here
// must be mirrored there until the two are unified.

final class LegacyReportBuilder
{
    public function build(int $month): string
    {
        return "report-{$month}.xlsx";
    }
}

----------[ Good ]----------

// Renders the monthly sales spreadsheet finance imports by hand.

final class MonthlySalesReport
{
    public function __construct(private readonly SalesGrouping $grouping) {}

    public function render(int $month): string
    {
        return $this->grouping->for($month) . "-report.xlsx";
    }
}
```

### bloated-docblock — in TypeScript

A type has several paragraphs of description

```ts
----------[ Bad ]----------

/**
 * A balance includes the cash float.
 *
 * This second paragraph belongs with the code that plans the operation.
 */
export class DrawerTotals {
    readonly value = 0
}

----------[ Good ]----------

/** A balance includes the cash float. */
export class DrawerTotalsDraft {}
```

### bloated-docblock — in Python

A type has several paragraphs of description

```py
----------[ Bad ]----------

@dataclass(frozen=True)
class DeliveryWindow:
    """The hours a courier may arrive at an address.

    It also knows which carriers serve the postcode, how the slot is priced at peak times, and when the
    warehouse has to have the parcel packed for it.
    """

    opens: int
    closes: int

----------[ Good ]----------

@dataclass(frozen=True)
class CourierWindow:
    """The hours a courier may arrive at an address.

    Attributes:
        opens: the first hour, 0-23.
        closes: the last hour, 0-23.
    """

    opens: int
    closes: int
```

### bloated-docblock — in C#

A type has several paragraphs of description

```cs
----------[ Bad ]----------

/// <summary>
/// Takes a basket through payment.
///
/// It also reserves the stock, books the courier, sends the confirmation email and records the sale in the
/// ledger, retrying each step that fails.
/// </summary>
public sealed class Checkout
{
    public int Steps => 5;
}

----------[ Good ]----------

/// <summary>Takes a basket through payment.</summary>
public sealed class PaymentStep
{
    public int Attempts => 3;
}
```

### bloated-docblock — in Go

A type has several paragraphs of description

```go
----------[ Bad ]----------

// DrawerTotals records a value the warehouse staff can review.
//
// This second paragraph belongs with the code that plans the operation.
type DrawerTotals struct{ Value int }

----------[ Good ]----------

// DrawerTotalsDraft is ready for comparison.
type DrawerTotalsDraft struct{ Draft int }
```

### ceremony-docblock — in PHP

A doc comment repeats the function signature

```php
----------[ Bad ]----------

public function format(int $sequence): string
{
    return sprintf('%s-%06d', $this->prefix, $sequence);
}

----------[ Good ]----------

// Renders the number finance quotes on a remittance — the sequence is zero-padded to six digits
// so invoices sort lexically in the ledger export.

public function formatForRemittance(int $sequence): string
{
    return sprintf('%s-%06d', $this->prefix, $sequence);
}
```

### ceremony-docblock — in TypeScript

A doc comment repeats the function signature

```ts
----------[ Bad ]----------

/**
 * @param {number} value
 * @returns {number}
 */
export function drawerAmount(value: number): number {
    return value
}

----------[ Good ]----------

export function drawerChecked(value: number): number {
    return Math.max(0, value)
}
```

### ceremony-docblock — in Python

A doc comment repeats the function signature

```py
----------[ Bad ]----------

def quote(parcel: Parcel, zone: str) -> int:
    """
    Args:
        parcel (Parcel):
        zone (str):

    Returns:
        int
    """
    return 500 + parcel.weight_grams // 100 + (200 if zone == "EU-1" else 0)

----------[ Good ]----------

def quote_cents(parcel: Parcel, zone: str) -> int:
    """The price of sending the parcel to the zone, in cents.

    Args:
        zone: a carrier zone code, such as "EU-1".
    """
    supplement = 200 if zone == "EU-1" else 0
    return supplement + 500 + parcel.weight_grams // 100
```

### ceremony-docblock — in C#

A doc comment repeats the function signature

```cs
----------[ Bad ]----------

/// <summary>
///
/// </summary>
/// <param name="invoiceNumber"></param>
/// <param name="copies"></param>
/// <returns></returns>
public int Queue(string invoiceNumber, int copies)
{
    for (var copy = 0; copy < copies; copy++)
    {
        queued.Add(invoiceNumber);
    }

    return queued.Count;
}

----------[ Good ]----------

/// <summary>Queues a copy for each page the customer asked to see again, and says how many now wait.</summary>
/// <param name="copies">How many copies the customer asked for; zero queues nothing.</param>
public int QueueFor(string invoiceNumber, int copies)
{
    queued.AddRange(Enumerable.Repeat(invoiceNumber, copies));

    return queued.Count;
}
```

### dangling-doc-reference — in PHP

A document reference points at a declaration the project does not have

```php
----------[ Bad ]----------

// Picks the cheapest carrier. Rates come from {@see \Shop\Shipping\RemovedRateBook} — deleted, so the
// link is stale.

public function cheapest(string $zone, float $weight): string
{
    return $weight > 10.0 ? "freight:{$zone}" : "parcel:{$zone}";
}

----------[ Good ]----------

// Picks the cheapest carrier for a heavy parcel. Rates come from
// {@see \Shop\Shipping\ShippingRateRegistry} — the class the rate book became, so the reference
// resolves.

public function cheapestFreight(string $zone): string
{
    return "freight:{$zone}";
}
```

### dangling-doc-reference — in TypeScript

A document reference points at a declaration the project does not have

```ts
----------[ Bad ]----------

export function readDrawerTotals(): number {
    return 1
}

----------[ Good ]----------

export function nextDrawerTotals(): number {
    return 2
}
```

### dangling-doc-reference — in Python

A document reference points at a declaration the project does not have

```py
----------[ Bad ]----------

def manifest(parcels: list) -> str:
    """One line per :class:`shop.dispatch_desk.Package` handed to the courier."""
    return "\n".join(str(parcel) for parcel in parcels)

----------[ Good ]----------

def manifest_lines(parcels: list) -> str:
    """One line per :class:`shop.dispatch_desk.Parcel` handed to the courier."""
    lines = [f"{index}. {parcel}" for index, parcel in enumerate(parcels, start=1)]
    return "\n".join(lines)
```

### dangling-doc-reference — in C#

A document reference points at a declaration the project does not have

```cs
----------[ Bad ]----------

/// <summary>Splits a <see cref="ShoppingCart"/> into the parcels that need wrapping.</summary>
public sealed class WrapStation
{
    public IReadOnlyList<string> Wrappable(IEnumerable<string> skus) => skus.Where(sku => !sku.StartsWith("DIG-")).ToList();
}

----------[ Good ]----------

/// <summary>Splits a <see cref="Basket"/> into the parcels that need wrapping.</summary>
public sealed class WrapCounter
{
    public int Count(IEnumerable<string> skus) => skus.Count(sku => !sku.StartsWith("DIG-"));
}
```

### dangling-doc-reference — in Go

A document reference points at a declaration the project does not have

```go
----------[ Bad ]----------

// readDrawerTotals details are written in [MissingTillReport].
func readDrawerTotals(drawers map[int][]int, shift int) DrawerTotals {
	total := 0
	for _, amount := range drawers[shift] {
		total += amount
	}
	return DrawerTotals{Value: total}
}

----------[ Good ]----------

// nextDrawerTotals details are written in [DrawerTotals].
func nextDrawerTotals() int {
	return 2
}
```

### negative-space-comment — in PHP

A comment defends the code against an assumption the reader has no reason to make

```php
----------[ Bad ]----------

public function get(string $code): SkuEntry
{
    // no magic here; a missing code yields an empty entry
    return new SkuEntry();
}

----------[ Good ]----------

// The affirmative form of the same note: it states what the registry DOES, so a reader never has
// to be talked out of a suspicion the code never raised.

public function entry(string $code): SkuEntry
{
    // an unknown code answers with an empty entry, so a caller never tests for absence
    return new SkuEntry();
}
```

### negative-space-comment — in Python

A comment defends the code against an assumption the reader has no reason to make

```py
----------[ Bad ]----------

# the delay is not random, it follows the attempt count
def backoff(attempt: int) -> int:
    return 2 ** attempt

----------[ Good ]----------

# the delay doubles with every attempt
def retry_delay(attempt: int) -> int:
    return min(2 ** attempt, 60)
```

### negative-space-comment — in C#

A comment defends the code against an assumption the reader has no reason to make

```cs
----------[ Bad ]----------

public string Winner(int week)
{
    // seeded by the week, not random
    var pick = new Random(week).Next(entrants.Count);

    return entrants[pick];
}

----------[ Good ]----------

public string RunnerUp(int week)
{
    // the same week always draws the same entrant, so a customer can check the result
    var pick = new Random(week * 31).Next(entrants.Count);

    return entrants[pick];
}
```

### restated-comment — in PHP

A comment repeats the words of the statement it documents

```php
----------[ Bad ]----------

public function load(int $cartId): void
{
    // store the cart snapshot
    $this->snapshot = CartSnapshot::of($cartId);
}

----------[ Good ]----------

// The comment now carries what the code cannot: WHY the snapshot is taken once.

public function open(int $cartId): void
{
    // the cart is frozen at checkout entry, so a coupon is weighed against the cart as it was then
    $this->snapshot = CartSnapshot::of($cartId);
}
```

### restated-comment — in Python

A comment repeats the words of the statement it documents

```py
----------[ Bad ]----------

def amount(self) -> int:
    # set the total to the lines sum
    total = sum(self.lines)
    return round(total * self.rate)

----------[ Good ]----------

def rounded_amount(self) -> int:
    # the tax office rounds each invoice once, never per line
    subtotal = sum(self.lines)
    return round(subtotal * self.rate)
```

### restated-comment — in C#

A comment repeats the words of the statement it documents

```cs
----------[ Bad ]----------

public decimal On(decimal subtotal)
{
    // set the tip from the subtotal and the percent
    var tip = subtotal * percent / 100;

    return Math.Round(tip, 2);
}

----------[ Good ]----------

public string Offered(decimal subtotal)
{
    var suggested = subtotal * percent / 100;

    return suggested.ToString("0.00");
}
```

### stacked-docblock — in PHP

Several doc comments describe one declaration

```php
----------[ Bad ]----------

// Writes the report where the export job expects it.

/**
 * The second block PHP never hands to a reader.
 */
public function save(): void
{
    // write to disk
}

----------[ Good ]----------

// Writes the report where the export job expects it — the second block folded in, so the one
// block PHP hands a reader says everything both used to.

public function archive(): void
{
    $this->save();
}
```

### stacked-docblock — in TypeScript

Several doc comments describe one declaration

```ts
----------[ Bad ]----------

/** A balance includes the cash float. */
/** This second doc comment has the same owner. */
export class DrawerTotalsReady {}

----------[ Good ]----------

/** A balance includes the cash float. */
export class DrawerTotalsPreview {}
```
