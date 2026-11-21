# Arguments: name the input and return the result — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### array-bag — in TypeScript

A loose map is read through fixed keys

```ts
----------[ Bad ]----------

export function hasRefund(
    slips: Record<string, number>,
): boolean {
    if (slips['paid'] === 0) {
        return false
    }
    return slips['refunded'] > 0
}

----------[ Good ]----------

export function makeAddress(street: string, district: string, postcode: string): AddressParts {
    return { street, district, postcode }
}
```

### array-bag — in Go

A loose map is read through fixed keys

```go
----------[ Bad ]----------

func discardsDeposit(
	slips map[string]int,
) bool {
	if slips["deposit"] == 0 {
		return true
	}
	return slips["collected"] < slips["cancellation"]
}

----------[ Good ]----------

func resolvedAddress(street string, district string, postcode string) shippingAddress {
	return shippingAddress{street: street, district: district, postcode: postcode}
}
```

### array-bag-parameter — in TypeScript

Callers pass a loose map with the same fields

```ts
----------[ Bad ]----------

function foldReceipt(
    costs: Record<string, number>,
): number {
    return Object.values(costs).reduce((sum, cost) => sum + cost, 0)
}

----------[ Good ]----------

export function addressLine(parts: AddressParts): string {
    return parts.street + ' ' + parts.district
}
```

### array-bag-parameter — in Go

Callers pass a loose map with the same fields

```go
----------[ Bad ]----------

func counterNotice(
	revisions map[string][]int,
	stream chan<- int,
) {
	for _, ids := range revisions {
		for _, called := range ids {
			stream <- called
		}
	}
}

----------[ Good ]----------

func addressText(address shippingAddress) string {
	return address.street + ", " + address.district
}
```

### array-return-bag — in TypeScript

A routine returns a loose map with fixed fields

```ts
----------[ Bad ]----------

export function receiptTotals(net: number) {
    const vat = net / 10
    return { gross: net + vat, vat, net }
}

----------[ Good ]----------

export function makeAddress(street: string, district: string, postcode: string): AddressParts {
    return { street, district, postcode }
}
```

### array-return-bag — in Go

A routine returns a loose map with fixed fields

```go
----------[ Bad ]----------

func receiptDefaults(net int) map[string]int {
	if net < 0 {
		return nil
	}
	tax := net / 10
	return map[string]int{"gross": net + tax, "vat": tax, "adjusted": net}
}

----------[ Good ]----------

func resolvedAddress(street string, district string, postcode string) shippingAddress {
	return shippingAddress{street: street, district: district, postcode: postcode}
}
```

### assembled-template — in TypeScript

A multiline template is split into lines and joined

```ts
----------[ Bad ]----------

export function deliveryCard(driver: string, stops: string[]): string {
    const departure = new Intl.ListFormat('en', { style: 'short' }).format(stops)
    return ['DAILY MANIFEST', departure, 'Driver on duty', driver].join('\n')
}

----------[ Good ]----------

export function printCard(name: string): string {
    return `Receipt:
${name}
Customer copy.`
}
```

### assembled-template — in Go

A multiline template is split into lines and joined

```go
----------[ Bad ]----------

func deliveryCard(stops []string, signed bool) string {
	signature := "Waiting for signature"
	if signed {
		signature = "Signed by recipient"
	}
	var route strings.Builder
	for index, stop := range stops {
		if index > 0 {
			route.WriteString(" → ")
		}
		route.WriteString(stop)
	}
	return strings.Join([]string{"Delivery manifest", route.String(), "Signature", signature}, "\n")
}

----------[ Good ]----------

func printReceiptCard(name string) string {
	return fmt.Sprintf(`Receipt:
%s
Customer copy.`, name)
}
```

### bare-state-predicate — in TypeScript

A Boolean method about its own state has the name of an action

```ts
----------[ Bad ]----------

opens(): boolean {
    return this.counter >= 1
}

----------[ Good ]----------

isOpen(): boolean {
    return this.active > 0
}
```

### bare-state-predicate — in Go

A Boolean method about its own state has the name of an action

```go
----------[ Bad ]----------

func (state ReceiptShapeState) Opens() bool {
	return state.counter >= 1
}

----------[ Good ]----------

func (a *addressCounter) IsOpen() bool { return a.active > 0 }
```

### computed-boolean-argument — in TypeScript

Callers repeatedly compute Boolean arguments from one object

```ts
----------[ Bad ]----------

export function freightAvailability(first: FreightArgument, second: FreightArgument): boolean[] {
    const one = freightReady(first.packed, first.insured)
    const two = freightReady(second.packed, second.insured)
    return [one, two]
}

----------[ Good ]----------

function canDispatch(details: LabelDetails): boolean {
    return details.paid && details.urgent
}
```

### computed-boolean-argument · hand-rolled-wither · keyed-lookup-envy · scratch-state-restore · unused-parameter — in C#

_computed-boolean-argument_ — Callers repeatedly compute Boolean arguments from one object

_hand-rolled-wither_ — A wither repeats fields from its type's constructor

_keyed-lookup-envy_ — A routine looks up a value using a field of another local type

_scratch-state-restore_ — A method saves a field, changes it, and restores it

_unused-parameter_ — The function never reads a declared parameter

```cs
----------[ Bad ]----------

public sealed class ReceiptRouting
{
    private string zone;
    private string depot;
    private int counter;

    public ReceiptRouting(string zone, string depot, int counter)
    {
        this.zone = zone;
        this.depot = depot;
        this.counter = counter;
    }

    private static bool NeedsReview(bool left, bool right)
    {
        return left && !right;
    }

    public bool Check(ReceiptRoute front, ReceiptRoute back)
    {
        var first = NeedsReview(front.Printed, front.Verified);
        var second = NeedsReview(back.Printed, back.Verified);
        return first || second;
    }

    private string Caption(string value,
        string prefix)
    {
        return this.zone + value;
    }

    public void Dispatches()
    {
        this.counter = this.counter + 1;
    }

    public ReceiptRouting WithCounter(int next)
    {
        return new ReceiptRouting(this.zone, this.depot, next);
    }

    public string Owner(Dictionary<string, string> owners, ReceiptRoute route)
    {
        return owners[route.Code];
    }

    public string Inspect(string probe)
    {
        var saved = this.zone;
        this.zone = probe;
        var caption = Caption(probe, this.depot);
        this.zone = saved;
        return caption;
    }

    public void ResetCounter()
    {
        this.counter = 0;
    }
}

----------[ Good ]----------

public sealed class ArgumentContracts
{
    public static bool CanDispatch(LabelDetails label)
    {
        return label.Paid && label.Checked;
    }

    public bool Accept(LabelDetails label)
    {
        return CanDispatch(label);
    }

    public string ChooseTitle(string title,
        string prefix)
    {
        return prefix + title;
    }

    public string PrintTitle(string title)
    {
        return title.Trim();
    }

    public LabelDetails Approve(LabelDetails label)
    {
        return label with { Checked = true };
    }

    public LabelDetails Pay(LabelDetails label)
    {
        return label with { Paid = true };
    }

    public string CompareLabel(LabelDetails label, string prefix)
    {
        return prefix + label.Text;
    }

    public string PrintLabel(LabelDetails label)
    {
        return label.Text;
    }

    public string ProbeLabel(LabelDetails label)
    {
        var saved = label.Text;
        var caption = saved.Trim();
        return caption;
    }

    public string InspectLabel(LabelDetails label, string probe)
    {
        return probe + label.Text;
    }
}
```

### computed-boolean-argument — in Go

Callers repeatedly compute Boolean arguments from one object

```go
----------[ Bad ]----------

func freightAvailability(first FreightArgument, second FreightArgument) [2]bool {
	one := freightReady(first.packed, first.insured)
	two := freightReady(second.packed, second.insured)
	return [2]bool{one, two}
}

----------[ Good ]----------

func canPrintCheckoutLabel(label checkoutLabel) bool {
	return label.paid && label.ready
}
```

### constructor-side-effect — in TypeScript

A constructor calls a collaborator and drops the result

```ts
----------[ Bad ]----------

constructor(printer: ReceiptShapePrinter) {
    printer.print(2, 25, 'Dock Street')
}

----------[ Good ]----------

dispatch(): void {
    const line = this.carrier.line()
    console.log(line)
}
```

### coupled-fields — in TypeScript

An operation changes and checks the same fields together

```ts
----------[ Bad ]----------

accept(counter: number, title: string): boolean {
    this.counter = counter
    this.title = title
    return this.counter > 0 && this.title.length >= 3
}

----------[ Good ]----------

update(active: number, name: string): number {
    this.active = active
    this.name = name
    return this.active
}
```

### coupled-fields — in Go

An operation changes and checks the same fields together

```go
----------[ Bad ]----------

func (state *ReceiptShapeState) Accept(counter int, slip string) bool {
	state.counter = counter
	state.slip = slip
	return state.counter > 0 && len(state.slip) > 3
}

----------[ Good ]----------

func (a *addressCounter) Rename(label string, active int) int {
	a.active = active
	a.label = label
	return a.active
}
```

### data-clump — in PHP

The same group of scalar parameters appears on different types

```php
----------[ Bad ]----------

// in Shop\Integrations\InventoryReconciler
public function reconcile(string $shopId, string $userId, string $channelId): bool
{
    foreach ($this->chunks() as $chunk) {
        if ($chunk > $this->batchSize) {
            return false;
        }
    }

    return $shopId !== '' && $userId !== '' && $channelId !== '';
}

// in Shop\Reporting\AccessAuditor
public function record(string $shopId, string $userId, string $channelId): string
{
    return implode(self::SEPARATOR, [$shopId, $userId, $channelId]);
}

// in Shop\Webhooks\OrderFeedPublisher
public function publish(string $shopId, string $userId, string $channelId): int
{
    $sent = count($this->pending);
    $this->pending = [];

    return $sent + strlen($shopId . $userId . $channelId);
}

----------[ Good ]----------

// in Shop\Reporting\AccessAuditor
public function recordAccess(AccessContext $context): string
{
    return implode(self::SEPARATOR, [$context->shopId, $context->userId, $context->channelId]);
}

final class AccessContext
{
    public function __construct(
        public readonly string $shopId,
        public readonly string $userId,
        public readonly string $channelId,
    ) {}
}
```

### data-clump — in TypeScript

The same group of scalar parameters appears on different types

```ts
----------[ Bad ]----------

// in receipt-value-shape.ts
print(copies: number, counterId: number, store: string): string[] {
    return Array.from({ length: copies }, (_, at) => store + '/' + counterId + '/' + at)
}

// in receipt-value-shape.ts
canSend(counterId: number, copies: number, store: string): boolean {
    if (store.length === 0 || copies < 1) {
        return false
    }
    return counterId >= 10
}

// in vessel-value-shape.ts
print(voyage: string, dock: string, vessel: string): string {
    return `${vessel.toUpperCase()} departs ${dock} on ${voyage}`
}

// in vessel-value-shape.ts
check(voyage: string, dock: string, vessel: string): boolean {
    if (voyage.length < 3 || dock.length < 2) return false
    return vessel.charAt(0) === dock.charAt(0)
}

// in wrapping-value-shape.ts
print(paper: string, ribbon: string, stamp: string): string {
    return [paper, ribbon].filter(Boolean).join(' + ') + ' / ' + stamp
}

// in wrapping-value-shape.ts
check(paper: string, ribbon: string, stamp: string): boolean {
    return paper.startsWith('kraft') || ribbon !== '' && stamp.toLowerCase() === 'fragile'
}

----------[ Good ]----------

current(parts: AddressParts): string {
    return parts.postcode
}
```

### data-clump — in Python

The same group of scalar parameters appears on different types

```py
----------[ Bad ]----------

# in customers.py
def subscribe(self, name: str, email: str, phone: str, opt_in: bool) -> None:
    self.list.add(email, name)

# in customers.py
def enrol(self, phone: str, name: str, email: str, opt_in: bool = False) -> None:
    self.members.add(name, email, phone, opt_in)

# in delivery.py
def book_delivery(street: str, city: str, postcode: str, carrier) -> str:
    return carrier.book(f"{street}, {postcode} {city}")

# in quotes.py
def quote(postcode: str, street: str, city: str, weight_kg) -> int:
    central = postcode.startswith("1") and street.strip() and city == "Amsterdam"
    return (495 if central else 695) + int(weight_kg * 20)

----------[ Good ]----------

# in delivery.py
@dataclass(frozen=True)
class Address:
    street: str
    city: str
    postcode: str

    def line(self) -> str:
        return f"{self.street}, {self.postcode} {self.city}"

# in delivery.py
def schedule_delivery(address: Address, carrier) -> str:
    return carrier.book(address.line())
```

### data-clump — in C#

The same group of scalar parameters appears on different types

```cs
----------[ Bad ]----------

// in LabelPrinter.cs
// A label is printed from a street, a city and a postcode — the same three values the quote takes, side
// by side, in another class: an address nobody named.
public sealed class LabelPrinter
{
    public string Print(string street, string city, string postcode) => $"{street}\n{postcode} {city}";

    public string PrintFor(Address address) => $"{address.Street}\n{address.Postcode} {address.City}";
}

// in Quotes.cs
public sealed class Quotes(decimal perKilometre)
{
    public decimal Price(string postcode, string city, string street) =>
        street.Length + city.Length > 40 ? perKilometre * 2 : postcode.StartsWith('1') ? perKilometre : perKilometre * 1.5m;
}

// in SalesReport.cs
// Sales and returns are both reported over a period — a start, an end and a time zone — passed as three
// loose values to each report, so "the period" is a notion only the parameter lists share.
public sealed class SalesReport(IReadOnlyList<(DateTime At, long Cents)> sales)
{
    public long Total(DateTime from, DateTime until, string zone) =>
        sales.Where(sale => sale.At >= from && sale.At < until && zone.Length > 0).Sum(sale => sale.Cents);
}

// in SalesReport.cs
public sealed class ReturnsReport(IReadOnlyList<(DateTime At, string Sku)> returns)
{
    public int Count(string zone, DateTime until, DateTime from) =>
        returns.Count(entry => entry.At >= from && entry.At < until && zone.Length > 0);
}

// in Listings.cs
// Two listings page through stock the same way — a page, a size and a sort order handed along together —
// so the paging rules live in two places and a third listing will copy them.
public sealed class ShelfListing(IReadOnlyList<string> skus)
{
    public IReadOnlyList<string> Page(int page, int size, bool descending) =>
        (descending ? skus.OrderDescending() : skus.Order()).Skip(page * size).Take(size).ToList();
}

// in Listings.cs
public sealed class BackorderListing(IReadOnlyList<(string Sku, int Missing)> backorders)
{
    public IReadOnlyList<string> Page(int page, int size, bool descending)
    {
        var ordered = descending ? backorders.OrderByDescending(entry => entry.Missing) : backorders.OrderBy(entry => entry.Missing);

        return ordered.Skip(page * size).Take(size).Select(entry => $"{entry.Sku}: {entry.Missing}").ToList();
    }
}

----------[ Good ]----------

// in LabelPrinter.cs
// A label is printed from a street, a city and a postcode — the same three values the quote takes, side
// by side, in another class: an address nobody named.
public sealed class LabelPrinter
{
    public string Print(string street, string city, string postcode) => $"{street}\n{postcode} {city}";

    public string PrintFor(Address address) => $"{address.Street}\n{address.Postcode} {address.City}";
}

// in LabelPrinter.cs
public sealed record Address(string Street, string City, string Postcode);
```

### data-clump — in Go

The same group of scalar parameters appears on different types

```go
----------[ Bad ]----------

// in receipt_value_shape.go
func (ReceiptShapePrinter) Print(copies int, counter int, store string) string {
	return fmt.Sprintf("%s counter %d, %d copies", store, counter, copies)
}

// in receipt_value_shape.go
func (ReceiptShapeValidator) Check(copies int, counter int, store string) bool {
	if store == "" || counter < 1 {
		return false
	}
	return copies > 0 && copies < 20
}

// in vessel_value_shape.go
func (VesselShapePrinter) Print(street string, district string, code string) string {
	return street + district + code
}

// in vessel_value_shape.go
func (VesselShapeValidator) Check(street string, district string, code string) bool {
	return street != district && len(code) > 1
}

// in wrapping_value_shape.go
func (WrappingShapePrinter) Measure(height int, width int, depth int) int {
	area := height*width + width*depth + depth*height
	return area * 2
}

// in wrapping_value_shape.go
func (WrappingShapeValidator) Fits(height int, width int, depth int) bool {
	return height < width+5 && depth > 0
}

// in wrapping_value_shape.go
// The receipt's copies, counter and store travel here too: one group across two files.
func (WrappingShapePrinter) Slip(copies int, counter int, store string) string {
	return store + " wrapped for counter " + strings.Repeat("|", counter) + strings.Repeat("*", copies)
}

----------[ Good ]----------

func (h historicalAddresses) Includes(address shippingAddress) bool {
	return len(h.rows) > 0 && h.rows[0].Equals(address)
}
```

### derived-argument — in TypeScript

Callers repeatedly pass pieces of the same object

```ts
----------[ Bad ]----------

export function freightReport(first: FreightArgument, second: FreightArgument): string[] {
    const one = freightDetails(first, first.label)
    const two = freightDetails(second, second.label)
    return [one, two]
}

----------[ Good ]----------

function formatDetails(details: LabelDetails): string {
    return details.code + (details.urgent ? ' express' : '')
}
```

### derived-argument — in Go

Callers repeatedly pass pieces of the same object

```go
----------[ Bad ]----------

func freightReport(first FreightArgument, second FreightArgument) [2]string {
	one := freightDetails(first, first.label)
	two := freightDetails(second, second.label)
	return [2]string{one, two}
}

----------[ Good ]----------

func paidCheckoutLabel(label checkoutLabel) string {
	if label.paid {
		return label.code + " paid"
	}
	return label.code
}
```

### feature-envy — in TypeScript

An operation reads several pieces of another local type

```ts
----------[ Bad ]----------

export function label(parcel: Parcel): Parcel {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
        parcel.zone = Math.max(parcel.zone, 1)
    } else if (parcel.grams > 500) {
        parcel.labels.push('medium')
        parcel.zone = Math.max(parcel.zone, 1)
    } else {
        parcel.zone = Math.max(parcel.zone, 1)
    }

    return parcel
}

----------[ Good ]----------

line(): string {
    return this.parts.street + ' ' + this.parts.district + ' ' + this.parts.postcode
}
```

### feature-envy — in Go

An operation reads several pieces of another local type

```go
----------[ Bad ]----------

func receiptDescription(receipt ReceiptShapeFields) string {
	if receipt.copies <= 0 {
		return fmt.Sprint(receipt.counter)
	}
	return fmt.Sprintf("%d copies at counter %d for %s", receipt.copies, receipt.counter, receipt.delivery.neighbourhood)
}

----------[ Good ]----------

func (a shippingAddress) Caption() string {
	return a.street + " " + a.district + " " + a.postcode
}
```

### filled-parameter — in TypeScript

The caller reads the result from a parameter the function filled

```ts
----------[ Bad ]----------

function freightFill(value: FreightArgument): void {
    value.packed = true
}

----------[ Good ]----------

function occupyLabel(details: LabelDetails): LabelDetails {
    return { ...details, paid: true }
}
```

### filled-parameter — in Go

The caller reads the result from a parameter the function filled

```go
----------[ Bad ]----------

func freightFill(value *FreightArgument) {
	value.packed = true
}

----------[ Good ]----------

func payCheckoutLabel(label checkoutLabel) checkoutLabel {
	label.paid = true
	return label
}
```

### flag-argument — in TypeScript

A Boolean parameter selects which operation runs

```ts
----------[ Bad ]----------

export function freightStatus(urgent: boolean, publish: (state: string) => void): void {
    if (urgent) {
        publish('packed')
    } else {
        publish('insured')
    }
}

----------[ Good ]----------

export function freightStatusOf(value: FreightArgument): string {
    return value.packed ? 'packed' : 'insured'
}
```

### flag-argument — in Go

A Boolean parameter selects which operation runs

```go
----------[ Bad ]----------

func freightStatus(urgent bool, queue chan<- string) {
	if urgent {
		queue <- "pack freight"
	} else {
		queue <- "await insurance"
	}
}

----------[ Good ]----------

func enqueueLabel(count int) int {
	return count + 1
}
```

### hand-rolled-wither — in TypeScript

A wither repeats fields from its type's constructor

```ts
----------[ Bad ]----------

withRefund(returned: boolean): ReceiptShapeValue {
    return new ReceiptShapeValue(this.total, this.currency, returned)
}

----------[ Good ]----------

withStreet(street: string): SelectedAddress {
    return new SelectedAddress({ ...this.value, street })
}
```

### handed-out-collection — in TypeScript

A routine exposes its own mutable collection

```ts
----------[ Bad ]----------

get counterReceipts(): Map<number, ReceiptShapeValue> {
    return this.receipts
}

----------[ Good ]----------

snapshot(): Map<number, ReceiptShapeValue> {
    return new Map(this.receipts)
}
```

### handed-out-collection — in Go

A routine exposes its own mutable collection

```go
----------[ Bad ]----------

func (shelf ReceiptShapeShelf) ByCounter() map[int]ReceiptShapeValue {
	return shelf.values
}

----------[ Good ]----------

func (shelf ReceiptShapeShelf) Snapshot() map[int]ReceiptShapeValue {
	return maps.Clone(shelf.values)
}
```

### keyed-lookup-envy — in TypeScript

A routine looks up a value using a field of another local type

```ts
----------[ Bad ]----------

export function receiptCounter(counters: Record<number, string>, fields: ReceiptShapeFields): string {
    return counters[fields.counterId]
}

----------[ Good ]----------

export class RoutingAddress {
    constructor(private readonly postcode: string) {}

    carrierFrom(carriers: Record<string, Carrier>): Carrier {
        return carriers[this.postcode]
    }
}
```

### keyed-lookup-envy — in Go

A routine looks up a value using a field of another local type

```go
----------[ Bad ]----------

func receiptCounterName(counters map[int]string, receipt ReceiptShapeFields) string {
	return counters[receipt.counter]
}

----------[ Good ]----------

func findAddress(addresses map[string]shippingAddress, postalCode string) shippingAddress {
	if len(addresses) == 0 {
		return shippingAddress{}
	}
	return addresses[postalCode]
}
```

### match-default-returns-null — in PHP

The default arm silently returns an absent value

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
public function strictFor(Product $product): string
{
    return match ($product->priority) {
        1 => 'urgent',
        2 => 'normal',
        3 => 'low',
        default => throw UnknownPriority::for($product->priority),
    };
}

final class UnknownPriority extends RuntimeException
{
    public static function for(int $priority): self
    {
        return new self("No label is mapped for priority {$priority}.");
    }
}
```

### match-default-returns-null — in TypeScript

The default arm silently returns an absent value

```ts
----------[ Bad ]----------

export function accountStageCaption(stage: AccountReferenceTreeStage): string | null {
    switch (stage) {
        case AccountReferenceTreeStage.Open: return 'open'
        case AccountReferenceTreeStage.Sealed: return 'sealed'
        default: return null
    }
}

----------[ Good ]----------

export function receiptStage(state: DispatchStage): string {
    switch (state) {
        case DispatchStage.Waiting:
            return 'waiting'
        case DispatchStage.Sent:
            return 'sent'
        default:
            throw new Error('Unexpected dispatch stage')
    }
}
```

### match-default-returns-null — in C#

The default arm silently returns an absent value

```cs
----------[ Bad ]----------

public static class CarrierLogos
{
    public static string? LogoFor(Courier courier) => courier switch
    {
        Courier.Postal => "postal.svg",
        Courier.Express => "express.svg",
        Courier.Freight => "freight.svg",
        _ => null,
    };

    public static string Logo(Courier courier) => courier switch
    {
        Courier.Postal => "postal.svg",
        Courier.Express => "express.svg",
        Courier.Freight => "freight.svg",
        _ => throw new ArgumentOutOfRangeException(nameof(courier), courier, null),
    };

    public static string? TrackingHost(Courier courier) => courier switch
    {
        Courier.Express => "track.express.example",
        _ => null,
    };
}

----------[ Good ]----------

public static class CarrierLogos
{
    public static string? LogoFor(Courier courier) => courier switch
    {
        Courier.Postal => "postal.svg",
        Courier.Express => "express.svg",
        Courier.Freight => "freight.svg",
        _ => null,
    };

    public static string Logo(Courier courier) => courier switch
    {
        Courier.Postal => "postal.svg",
        Courier.Express => "express.svg",
        Courier.Freight => "freight.svg",
        _ => throw new ArgumentOutOfRangeException(nameof(courier), courier, null),
    };

    public static string? TrackingHost(Courier courier) => courier switch
    {
        Courier.Express => "track.express.example",
        _ => null,
    };
}
```

### match-default-returns-null — in Go

The default arm silently returns an absent value

```go
----------[ Bad ]----------

func bicycleVoucherNotices(state BicycleVoucherStatus, codes []string, output chan<- string) []string {
	switch state {
	case voucherHeld:
		published := make([]string, 0, len(codes))
		for _, code := range codes {
			output <- code
			published = append(published, code)
		}
		return published
	case voucherCancelled:
		return append([]string{"voided"}, codes...)
	default:
		return nil
	}
}

----------[ Good ]----------

func forwardingStageCaption(stage forwardingStage) string {
	switch stage {
	case forwardingWaiting:
		return "waiting"
	case forwardingSent:
		return "sent"
	default:
		panic("unexpected stage")
	}
}
```

### member-after-method · member-out-of-order — in TypeScript

_member-after-method_ — A field is declared below a method

_member-out-of-order_ — A constant is declared below an instance field

```ts
----------[ Bad ]----------

export class DeliverySettings {
    private readonly queued = new Map<number, string[]>()

    static readonly MaxDetours = 4

    schedule(route: number, stops: string[]): void {
        this.queued.set(route, stops.slice(0, DeliverySettings.MaxDetours + 1))
    }

    private next = 0

    take(path: number): number {
        const existing = this.queued.get(path)
        if (existing !== undefined && existing.length > 0) this.next++
        return this.next
    }
}

----------[ Good ]----------

export class PrintSettings {
    static readonly CopiesLimit = 8

    private copies = 1

    printCount(): number {
        return this.copies
    }

    get spareCopies(): number {
        return PrintSettings.CopiesLimit - this.copies
    }
}
```

### mutable-static-state — in TypeScript

An operation writes shared static state

```ts
----------[ Bad ]----------

export function receiptTick(written: readonly number[]): number {
    for (const units of written) {
        receiptUnits += units
    }
    return receiptUnits
}

----------[ Good ]----------

increment(): void {
    this.active += 1
}
```

### mutable-static-state — in Go

An operation writes shared static state

```go
----------[ Bad ]----------

func receiptTick(copies int) {
	for copy := 0; copy < copies; copy++ {
		receiptCount += copy + 1
	}
}

----------[ Good ]----------

func (a *addressCounter) Tally() { a.active = a.active + 1 }
```

### mutable-value-object — in TypeScript

A value type changes a field after construction

```ts
----------[ Bad ]----------

addDeposit(paid: number): void {
    if (paid > 0) {
        this.total += paid
    }
}

----------[ Good ]----------

withStreet(street: string): SelectedAddress {
    return new SelectedAddress({ ...this.value, street })
}
```

### mutable-value-object — in Go

A value type changes a field after construction

```go
----------[ Bad ]----------

func (receipt *ReceiptShapeValue) AddDeposit(paid int) {
	if paid > 0 {
		receipt.amount += paid
	}
}

----------[ Good ]----------

func (a shippingAddress) WithStreet(street string) shippingAddress {
	return shippingAddress{street: street, district: a.district, postcode: a.postcode}
}
```

### narrated-command — in TypeScript

A command method is named in the third person

```ts
----------[ Bad ]----------

prints(): void {
    this.counter += 1
    this.title = 'Copy ' + this.counter
}

----------[ Good ]----------

clear(): void {
    this.name = ''
}
```

### narrated-command — in C#

A command method is named in the third person

```cs
----------[ Bad ]----------

public sealed class ReceiptRouting
{
    private string zone;
    private string depot;
    private int counter;

    public ReceiptRouting(string zone, string depot, int counter)
    {
        this.zone = zone;
        this.depot = depot;
        this.counter = counter;
    }

    private static bool NeedsReview(bool left, bool right)
    {
        return left && !right;
    }

    public bool Check(ReceiptRoute front, ReceiptRoute back)
    {
        var first = NeedsReview(front.Printed, front.Verified);
        var second = NeedsReview(back.Printed, back.Verified);
        return first || second;
    }

    private string Caption(string value,
        string prefix)
    {
        return this.zone + value;
    }

    public void Dispatches()
    {
        this.counter = this.counter + 1;
    }

    public ReceiptRouting WithCounter(int next)
    {
        return new ReceiptRouting(this.zone, this.depot, next);
    }

    public string Owner(Dictionary<string, string> owners, ReceiptRoute route)
    {
        return owners[route.Code];
    }

    public string Inspect(string probe)
    {
        var saved = this.zone;
        this.zone = probe;
        var caption = Caption(probe, this.depot);
        this.zone = saved;
        return caption;
    }

    public void ResetCounter()
    {
        this.counter = 0;
    }
}

----------[ Good ]----------

public sealed class ReceiptRouting
{
    private string zone;
    private string depot;
    private int counter;

    public ReceiptRouting(string zone, string depot, int counter)
    {
        this.zone = zone;
        this.depot = depot;
        this.counter = counter;
    }

    private static bool NeedsReview(bool left, bool right)
    {
        return left && !right;
    }

    public bool Check(ReceiptRoute front, ReceiptRoute back)
    {
        var first = NeedsReview(front.Printed, front.Verified);
        var second = NeedsReview(back.Printed, back.Verified);
        return first || second;
    }

    private string Caption(string value,
        string prefix)
    {
        return this.zone + value;
    }

    public void Dispatches()
    {
        this.counter = this.counter + 1;
    }

    public ReceiptRouting WithCounter(int next)
    {
        return new ReceiptRouting(this.zone, this.depot, next);
    }

    public string Owner(Dictionary<string, string> owners, ReceiptRoute route)
    {
        return owners[route.Code];
    }

    public string Inspect(string probe)
    {
        var saved = this.zone;
        this.zone = probe;
        var caption = Caption(probe, this.depot);
        this.zone = saved;
        return caption;
    }

    public void ResetCounter()
    {
        this.counter = 0;
    }
}
```

### narrated-command — in Go

A command method is named in the third person

```go
----------[ Bad ]----------

func (state *ReceiptShapeState) Prints() {
	state.counter += 1
	state.slip = fmt.Sprintf("receipt-%04d", state.counter)
}

----------[ Good ]----------

func (a *addressCounter) Clear() { a.label = "" }
```

### param-resolved-from-param — in TypeScript

One parameter is only used to resolve another parameter

```ts
----------[ Bad ]----------

export function carrierByCode(carriers: Record<string, Carrier>, code: string): Carrier {
    return carriers[code]
}

----------[ Good ]----------

function readCode(details: LabelDetails): string {
    return details.code
}
```

### param-resolved-from-param — in Go

One parameter is only used to resolve another parameter

```go
----------[ Bad ]----------

func freightFind(choices map[string]FreightArgument, key string) string {
	return choices[key].label
}

----------[ Good ]----------

func checkoutCode(label checkoutLabel) string {
	return label.code
}
```

### positional-tuple-return — in TypeScript

A return tuple has interchangeable unnamed slots

```ts
----------[ Bad ]----------

export function deliveryRange(stops: string[], fallback: string): [string, string] {
    const first = stops[0] ?? fallback
    return [first, stops[stops.length - 1] ?? first]
}

----------[ Good ]----------

export function deliveryBounds(stops: string[], fallback: string): [first: string, last: string] {
    const [initial, ...remaining] = stops
    const firstStop = initial ?? fallback
    return [firstStop, remaining.at(-1) ?? firstStop]
}
```

### redundant-arrow-return-type — in PHP

An arrow repeats the type already inferred from its expression

```php
----------[ Bad ]----------

public function factory(): callable
{
    return fn (): FeatureGate => new FeatureGate($this->environment);
}

----------[ Good ]----------

public function overridesReader(): callable
{
    return fn () => $this->overrides;
}
```

### redundant-arrow-return-type — in TypeScript

An arrow repeats the type already inferred from its expression

```ts
----------[ Bad ]----------

export const accountScore = (value: number): number => value - 2

----------[ Good ]----------

export const netDiscount = (price: number) => price - 1
```

### scratch-state-restore — in TypeScript

A method saves a field, changes it, and restores it

```ts
----------[ Bad ]----------

assessTitle(prefix: string): string {
    const saved = this.title
    this.title = prefix + this.title
    const result = this.title.toUpperCase()
    this.title = saved
    return result
}

----------[ Good ]----------

inspect(value: number): string {
    return value.toString() + this.name
}
```

### scratch-state-restore — in Go

A method saves a field, changes it, and restores it

```go
----------[ Bad ]----------

func (state *ReceiptShapeState) ProbeSlip(prefix string) string {
	kept := state.slip
	state.slip = prefix + state.slip
	displayed := fmt.Sprintf("%d %s", state.counter, state.slip)
	state.slip = kept
	return displayed
}

----------[ Good ]----------

func (a addressCounter) Inspect(prefix string) string {
	return prefix + a.label
}
```

### unused-parameter — in TypeScript

The function never reads a declared parameter

```ts
----------[ Bad ]----------

export function freightDisplay(
    value: FreightArgument,
    format: string,
): string {
    return value.label
}

----------[ Good ]----------

export function freightFormat(value: FreightArgument, format: string): string {
    return format + value.label
}
```

### unused-parameter — in Python

The function never reads a declared parameter

```py
----------[ Bad ]----------

def receipt(
    total: float,
    currency: str,
):
    if total <= 0:
        return 0
    adjusted = round(total, 2)
    return min(adjusted, 2_000)

----------[ Good ]----------

def book_title(title: str) -> str:
    return title.title()
```

### unused-parameter — in Go

The function never reads a declared parameter

```go
----------[ Bad ]----------

func freightDisplay(
	value FreightArgument,
	format string,
) string {
	return value.label
}

----------[ Good ]----------

func plainCheckoutCode(label checkoutLabel) string {
	return label.code
}
```
