# Dependencies: keep imports going in one direction — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### concrete-cross-module-dependency — in PHP

A constructor takes another module's concrete service

```php
----------[ Bad ]----------

public function __construct(private readonly ProductRepository $products) {}

----------[ Good ]----------

// in Shop\Harvest\YieldForecast
public function __construct(
    private CropCalendar $crops,
    private WateringCalendar $water,
) {}

// in Shop\Irrigation\WaterSchedule
public function litresDueOn(string $bed, int $day): int
{
    return (int) $this->database->table('watering')->where('bed', $bed)->where('day', $day)->sum('litres');
}

interface WateringCalendar
{
    public function litresDueOn(string $bed, int $day): int;
}
```

### concrete-cross-module-dependency — in TypeScript

A constructor takes another module's concrete service

```ts
----------[ Bad ]----------

constructor(
    private readonly service: AccountReferenceTreeService,
) {}

----------[ Good ]----------

constructor(
    private readonly formatter: ReceiptFormatter,
    private readonly reference: string,
) {}
```

### concrete-cross-module-dependency — in Python

A constructor takes another module's concrete service

```py
----------[ Bad ]----------

def __init__(
    self,
    tracking: ParcelTreeService,
    event: str,
    subscribers: list[str],
):
    self.tracking = tracking
    self.pending = [f"{event}/{name}" for name in subscribers if name]

----------[ Good ]----------

def __init__(
    self,
    receipt: ReceiptFormatter,
    reference: str,
):
    self.receipt = receipt
    self.reference = reference
```

### concrete-cross-module-dependency — in C#

A constructor takes another module's concrete service

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeOwner
{
    private readonly ParcelSettingsTreeImportedService reader;

    public ParcelSettingsTreeOwner(
        ParcelSettingsTreeImportedService reader)
    {
        this.reader = reader;
    }

    public string Record() { return this.reader.Read() + "!"; }
}

----------[ Good ]----------

public sealed class ReceiptJob
{
    private readonly IReceiptFormatter formatter;
    private readonly string reference;

    public ReceiptJob(
        IReceiptFormatter formatter,
        string reference)
    {
        this.formatter = formatter;
        this.reference = reference;
    }

    public string Caption()
    {
        return "Customer copy: " + this.FormatCurrent();
    }

    private string FormatCurrent()
    {
        return this.formatter.FormatNumber(this.reference);
    }

    public string Key
    {
        get { return this.reference; }
    }
}
```

### concrete-cross-module-dependency — in Go

A constructor takes another module's concrete service

```go
----------[ Bad ]----------

func NewNightAudit(
	audit *typeports.TillGraphService,
	receipts <-chan string,
	expected int,
) NightAudit {
	closed := make([]string, 0, expected)
	for ticket := range receipts {
		if ticket != "" {
			closed = append(closed, ticket)
		}
	}
	return NightAudit{source: audit, finished: closed}
}

----------[ Good ]----------

func NewReceiptJob(
	renderer receiptRenderer,
) *receiptJob {
	return &receiptJob{renderer: renderer}
}
```

### guard-beside-transition — in TypeScript

A caller checks another object's state before telling it to act

```ts
----------[ Bad ]----------

export function accountSeal(batch: AccountReferenceTreeBatch): void {
    if (batch.stage !== AccountReferenceTreeStage.Open) {
        throw new Error('Already closed')
    }
    batch.seal()
}

----------[ Good ]----------

dispatch(): void {
    if (this.stage !== DispatchStage.Waiting) {
        throw new Error('Message already sent')
    }
    this.stage = DispatchStage.Sent
}
```

### guard-beside-transition — in Python

A caller checks another object's state before telling it to act

```py
----------[ Bad ]----------

def seal_parcel_tree(batches: dict[int, ParcelTreeBatch], receipt) -> int:
    sealed = 0
    for dock, active in batches.items():
        if dock < 1:
            continue
        if ParcelTreeStage.SEALED == active.stage:
            raise ValueError("This bay has already sealed its parcels")
        active.seal()
        receipt(dock)
        sealed += 1
    return sealed

----------[ Good ]----------

def collect(self, ready: bool) -> CounterTicket:
    if not ready:
        raise ManifestUnreadable.missing_ticket()
    return self.current
```

### guard-beside-transition — in C#

A caller checks another object's state before telling it to act

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeSender
{
    public void Dispatch(ParcelSettingsTreeBatch batch)
    {
        if (batch.Stage != ParcelSettingsTreeStage.Open)
        {
            throw new InvalidOperationException();
        }
        batch.Seal();
    }
}

----------[ Good ]----------

public sealed class ReceiptDispatch
{
    private bool queued = true;

    public void Dispatch()
    {
        if (!this.queued)
        {
            throw new System.InvalidOperationException();
        }
        this.queued = false;
    }
}
```

### guard-beside-transition — in Go

A caller checks another object's state before telling it to act

```go
----------[ Bad ]----------

func sealParcelGraph(batches map[int]*ParcelGraphBatch, requested []int) {
	for _, number := range requested {
		batch, exists := batches[number]
		if !exists {
			continue
		}
		if batch.Stage != ParcelGraphOpen {
			panic("already closed")
		}
		batch.Seal()
		delete(batches, number)
	}
}

----------[ Good ]----------

func (r *forwardingReceipt) Dispatch() {
	if r.stage != forwardingWaiting {
		panic("already dispatched")
	}
	r.stage = forwardingSent
}
```

### hand-rolled-registry — in TypeScript

A type repeats a checked keyed store

```ts
----------[ Bad ]----------

export class AccountReferenceTreeDirectory {
    private entries: Record<string, string> = {}

    put(key: string, value: string): void {
        this.entries[key] = value
    }

    lookup(key: string): string {
        if (this.entries[key] === undefined) {
            throw new Error('Unknown key')
        }
        return this.entries[key]
    }
}

----------[ Good ]----------

export class ReceiptHistory {
    private readonly tags: Record<string, string> = {}

    remember(key: string, text: string): void {
        this.tags[key] = text
    }

    tag(key: string): string | undefined {
        return this.tags[key]
    }
}
```

### hand-rolled-registry — in Python

A type repeats a checked keyed store

```py
----------[ Bad ]----------

class ParcelTreeDirectory:
    def __init__(self, manifest: str):
        self.arrival: dict[str, int] = {}
        self.manifest = manifest

    def book(self, shipment: str, minute: int) -> None:
        self.arrival[shipment] = minute

    def notify(self, shipment: str, send) -> None:
        if self.arrival[shipment] < 0:
            raise KeyError(shipment)
        send(self.manifest, shipment, self.arrival[shipment])

----------[ Good ]----------

class ReceiptHistory:
    def __init__(self):
        self.tags: dict[str, str] = {}

    def remember(self, key: str, text: str) -> None:
        self.tags[key] = text

    def tag(self, key: str) -> str | None:
        return self.tags.get(key)
```

### hand-rolled-registry — in C#

A type repeats a checked keyed store

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeRegistry
{
    private readonly Dictionary<string, string?> entries = new();
    public void Put(string key, string value) { this.entries[key] = value; }
    public string? Read(string key)
    {
        if (this.entries[key] == null)
        {
            throw new KeyNotFoundException();
        }
        return this.entries[key];
    }
}

----------[ Good ]----------

public sealed class ReceiptHistory
{
    private readonly System.Collections.Generic.Dictionary<string, string> tags = new();

    public bool HasTag(string key)
    {
        return this.tags.ContainsKey(key);
    }
}
```

### hand-rolled-registry — in Go

A type repeats a checked keyed store

```go
----------[ Bad ]----------

type BicycleVoucherArchive struct {
	issued   map[string]int64
	redeemed int64
}

----------[ Good ]----------

func (h receiptHistory) Tag(key string) (string, bool) {
	tag, found := h.tags[key]
	return tag, found
}
```

### interface-overlap — in PHP

An interface repeats the signatures of a contract it can reuse.

```php
----------[ Bad ]----------

interface StockFiltersWithLabels
{
    public function region(): ?string;
    public function warehouses(): array;
    public function labels(int $warehouse, \DateTimeImmutable $countedOn, array $availableBrands = []): array;
}

----------[ Good ]----------

interface WarehouseStockFilters extends StockFilters
{
    public function labels(int $warehouse, \DateTimeImmutable $countedOn, array $availableBrands = []): array;
}
```

### interface-overlap — in Go

An interface repeats the signatures of a contract it can reuse.

```go
----------[ Bad ]----------

type LabeledInvoiceTotals interface {
	Taxes(number int) map[string]int
	Label(number int) string
	Net(number int) int
}

----------[ Good ]----------

type NamedInvoiceTotals interface {
	InvoiceTotals
	Label(invoice int) string
}
```

### lone-implementer-interface — in PHP

An interface has only one local implementation

```php
----------[ Bad ]----------

interface BinLabeller
{
    public function label(string $bin): string;

    public function reprint(string $bin, int $copies): string;

    public function calibrate(float $darkness, int $speedInchesPerSecond): void;
}

----------[ Good ]----------

final class VoucherReissuer
{
    public function __construct(private readonly RandomCodeGenerator $codes) {}

    public function reissue(string $lost): string
    {
        return $lost.'-R-'.$this->codes->next();
    }
}
```

### lone-implementer-interface — in TypeScript

An interface has only one local implementation

```ts
----------[ Bad ]----------

export interface AccountReferenceTreePort {
    exportAccountReferenceTree(): string
}

----------[ Good ]----------

interface ReceiptFormatter {
    format(reference: string): string
}
```

### lone-implementer-interface — in Python

An interface has only one local implementation

```py
----------[ Bad ]----------

class ParcelTreePort(Protocol):
    def send(self, carrier: str, dispatches: list[bytes]) -> int: ...
    def receive(self, token: bytes) -> bytes: ...

----------[ Good ]----------

class ReceiptFormatter(Protocol):
    def format_number(self, reference: str) -> str:
        ...
```

### lone-implementer-interface — in C#

An interface has only one local implementation

```cs
----------[ Bad ]----------

// A label printer behind the shop's own interface. The interface is the shop's, so it can answer a task, and
// blocking here holds the caller's thread for work the shop itself could have awaited.
public interface ILabelPrinter
{
    string Print(string id);
}

----------[ Good ]----------

public interface IReceiptFormatter
{
    string FormatNumber(string reference);
}
```

### lone-implementer-interface — in Go

An interface has only one local implementation

```go
----------[ Bad ]----------

type BicycleVoucherDesk interface {
	ClaimVoucher(voucher string) bool
	CancelClaim(voucher string)
	OpenSlots() int
	ClaimCount() int
}

----------[ Good ]----------

type receiptRenderer interface{ ReceiptTitle() string }
```

### middle-man — in TypeScript

A type forwards its methods to one field

```ts
----------[ Bad ]----------

export class AccountReferenceTreeForwarder {
    constructor(private readonly source: AccountReferenceTreeSource) {}

    read(): string {
        return this.source.read()
    }

    record(name: string): string {
        return this.source.record(name)
    }
}

----------[ Good ]----------

caption(): string {
    return this.formatCurrent() + ': closed'
}
```

### middle-man — in Python

A type forwards its methods to one field

```py
----------[ Bad ]----------

class ParcelTreeForwarder:
    def __init__(self, carrier: ParcelTreeSource):
        self.carrier = carrier

    def dispatch(self, grams: int, when: str) -> float:
        return self.carrier.dispatch(grams, when)

    def cancel_shipment(self, late: str) -> None:
        self.carrier.cancel_shipment(late)

----------[ Good ]----------

def caption(self) -> str:
    return "Customer copy: " + self._format_current()
```

### middle-man — in C#

A type forwards its methods to one field

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeForwarder
{
    private readonly ParcelSettingsTreeSource source;
    public ParcelSettingsTreeForwarder(ParcelSettingsTreeSource source) { this.source = source; }
    public string Read() { return this.source.Read(); }
    public string Record(string tag) { return this.source.Record(tag); }
}

----------[ Good ]----------

public sealed class ReceiptJob
{
    private readonly IReceiptFormatter formatter;
    private readonly string reference;

    public ReceiptJob(
        IReceiptFormatter formatter,
        string reference)
    {
        this.formatter = formatter;
        this.reference = reference;
    }

    public string Caption()
    {
        return "Customer copy: " + this.FormatCurrent();
    }

    private string FormatCurrent()
    {
        return this.formatter.FormatNumber(this.reference);
    }

    public string Key
    {
        get { return this.reference; }
    }
}
```

### middle-man — in Go

A type forwards its methods to one field

```go
----------[ Bad ]----------

type BicycleVoucherForwarder struct {
	stand *BicycleVoucherStand
}

----------[ Good ]----------

func (j *receiptJob) Caption() string { return "Customer copy: " + j.currentTitle() }
```

### namespace-cycle · namespace-dependency — in TypeScript

_namespace-cycle_ — Two independent modules import each other

_namespace-dependency_ — An import crosses a layer boundary its source may not use

```ts
----------[ Bad ]----------

import { caption } from '../view/caption'

----------[ Good ]----------

import { fallback } from '../core/defaults'
```

### namespace-dependency — in Go

An import crosses a layer boundary its source may not use

```go
----------[ Bad ]----------

import inventoryView "commandments.fixture/shop/layers/inventory/view"

----------[ Good ]----------

const Caption = "routing"
```

### refused-bequest — in TypeScript

An override only throws

```ts
----------[ Bad ]----------

override read(): string {
    throw new Error('Unavailable')
}

----------[ Good ]----------

override format(reference: string): string {
    return 'Customer copy: ' + super.format(reference)
}
```

### refused-bequest — in Python

An override only throws

```py
----------[ Bad ]----------

def read(self) -> str:
    raise NotImplementedError()

----------[ Good ]----------

def format_number(self, reference: str) -> str:
    return "Customer copy: " + super().format_number(reference)
```

### refused-bequest — in C#

An override only throws

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeBroken : ParcelSettingsTreeSource
{
    public override string Read() { throw new NotSupportedException(); }
}

----------[ Good ]----------

public sealed class LongReceipt : PlainReceipt
{
    public override string FormatNumber(string reference)
    {
        return "Customer copy: " + base.FormatNumber(reference);
    }
}
```

### train-wreck — in TypeScript

A chain reaches through three local types

```ts
----------[ Bad ]----------

export function accountRead(shelf: AccountReferenceTreeShelf): string {
    return shelf.bin.label.text
}

----------[ Good ]----------

export function receiptCaption(receipt: ReceiptJob): string {
    return receipt.caption()
}
```

### train-wreck — in Python

A chain reaches through three local types

```py
----------[ Bad ]----------

def read_parcel_tree(
    shelf: ParcelTreeShelf, region: str, codes: dict[str, int], send,
) -> None:
    label = shelf.bin.label.text
    cost = codes.get(region + "/" + label, 0)
    send((region, label, cost))

----------[ Good ]----------

def receipt_caption(job: ReceiptJob) -> str:
    return job.caption()
```

### train-wreck — in C#

A chain reaches through three local types

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeDisplay
{
    public string Read(ParcelSettingsTreeShelf shelf)
    {
        return shelf.Bin.Label.Text;
    }

    private string Draft() { return "parcel"; }

    public int Weight
    {
        get { return 8; }
    }
}

----------[ Good ]----------

public sealed class ReceiptIdentity
{
    public string ReceiptCaption(ReceiptJob job)
    {
        return job.Caption();
    }
}
```

### train-wreck — in Go

A chain reaches through three local types

```go
----------[ Bad ]----------

func readParcelGraph(shelf ParcelGraphShelf) string {
	return shelf.Bin.Label.Text
}

----------[ Good ]----------

func checkoutReceiptCaption(job *receiptJob) string { return job.Caption() }
```

### undeclared-member — in PHP

A member access the receiver's declared type neither declares nor inherits

```php
----------[ Bad ]----------

public function copy(BinLabel $label): string
{
    return $label->text().' ('.$label->barcode.')';
}

----------[ Good ]----------

// in Shop\Bins\BinLabelCopies
public function copyScannable(ScannableBinLabel $label): string
{
    return $label->text().' ('.$label->barcode.')';
}

interface ScannableBinLabel extends BinLabel
{
    public string $barcode { get; }
}
```

### unread-collaborator — in TypeScript

A collaborator is stored and never read

```ts
----------[ Bad ]----------

constructor(
    private readonly source: AccountReferenceTreeSource,
) {}

----------[ Good ]----------

constructor(
    private readonly formatter: ReceiptFormatter,
    private readonly reference: string,
) {}
```

### unread-collaborator — in Go

A collaborator is stored and never read

```go
----------[ Bad ]----------

type BicycleVoucherReport struct {
	issuedToday uint
	redeemed    uint
	totals      []int64
	archive *BicycleVoucherArchive
}

----------[ Good ]----------

type receiptJob struct {
	renderer receiptRenderer
}
```

### unused-private-member — in TypeScript

A private member has no readers

```ts
----------[ Bad ]----------

private draft(): string {
    return 'account'
}

----------[ Good ]----------

private formatCurrent(): string {
    return this.formatter.format(this.reference)
}
```

### unused-private-member — in Python

A private member has no readers

```py
----------[ Bad ]----------

def _preflight(self, gate: str) -> bytes:
    marker = bytes([self.depart])
    return marker + b"|".join(self.routes.get(gate, []))

----------[ Good ]----------

def _format_current(self) -> str:
    return self.receipt.format_number(self.reference)
```

### unused-private-member · useless-property-hook — in C#

_unused-private-member_ — A private member has no readers

_useless-property-hook_ — A getter reads no state on its object

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeDisplay
{
    public string Read(ParcelSettingsTreeShelf shelf)
    {
        return shelf.Bin.Label.Text;
    }

    private string Draft() { return "parcel"; }

    public int Weight
    {
        get { return 8; }
    }
}

----------[ Good ]----------

public sealed class ReceiptJob
{
    private readonly IReceiptFormatter formatter;
    private readonly string reference;

    public ReceiptJob(
        IReceiptFormatter formatter,
        string reference)
    {
        this.formatter = formatter;
        this.reference = reference;
    }

    public string Caption()
    {
        return "Customer copy: " + this.FormatCurrent();
    }

    private string FormatCurrent()
    {
        return this.formatter.FormatNumber(this.reference);
    }

    public string Key
    {
        get { return this.reference; }
    }
}
```

### unused-private-member — in Go

A private member has no readers

```go
----------[ Bad ]----------

func (report BicycleVoucherReport) stranded() uint {
	if report.issuedToday < report.redeemed {
		return 0
	}
	return report.issuedToday - report.redeemed
}

----------[ Good ]----------

func (j *receiptJob) currentTitle() string { return j.renderer.ReceiptTitle() }
```

### useless-property-hook — in TypeScript

A getter reads no state on its object

```ts
----------[ Bad ]----------

get weight(): number {
    return 12
}

----------[ Good ]----------

get key(): string {
    return this.reference
}
```
