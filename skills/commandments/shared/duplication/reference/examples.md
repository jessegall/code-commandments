# Duplication: give repeated code one owner — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### converted-argument — in PHP

Most callers convert a value the same way for one parameter

```php
----------[ Bad ]----------

// in Shop\Shelving\ShelfImporter
public function import(string $heading, int $bay): void
{
    $this->index->reserve(SlugText::of($heading), $bay);
}

// in Shop\Shelving\ShelfPlanner
public function plan(array $aisles): void
{
    foreach ($aisles as $bay => $name) {
        $this->index->reserve(SlugText::of($name), $bay);
    }
}

// in Shop\Wire\HotkeyBinding
public function bind(string $node): WireMessage
{
    return WireMessage::raise(SignalAlias::of(HotkeyPressed::class), $node);
}

// in Shop\Wire\PointerBinding
public function bind(string $node): WireMessage
{
    $this->bound[] = $node;

    return WireMessage::raise(SignalAlias::of(PointerReleased::class), $node);
}

----------[ Good ]----------

// in Shop\Wire\HotkeyBinding
// The FIX: the parameter is declared in the currency the caller holds, and the conversion lives on
// the far side of the call.

public function bindDirect(string $node): WireMessage
{
    return WireMessage::raiseFor(HotkeyPressed::class, $node);
}

// in Shop\Wire\WireMessage
public static function raiseFor(string $signal, string $target): self
{
    return new self(SignalAlias::of($signal), $target);
}
```

### converted-argument — in TypeScript

Most callers convert a value the same way for one parameter

```ts
----------[ Bad ]----------

// in freight-arguments.ts
export function freightLabels(first: FreightArgument, second: FreightArgument): string[] {
    const one = freightLabel(freightValue(first))
    const two = freightLabel(freightValue(second))
    return [one, two]
}

// in freight-manifest.ts
export function publishManifest(items: FreightArgument[], print: (label: string) => void): void {
    for (const item of items) {
        if (item.insured) {
            print(freightLabel(freightValue(item)))
        }
    }
}

// in rebate-arguments.ts
export function rebatesByPart(claims: Map<string, RebateClaim>): Map<string, string> {
    const displayed = new Map<string, string>()
    for (const [name, claim] of claims) {
        displayed.set(name, formattedCredit(claimedCents(claim)))
    }
    return displayed
}

// in rebate-arguments.ts
export function describeLatestRebate(order: RebateClaim | undefined): string {
    if (order === undefined) return 'none issued'
    return formattedCredit(claimedCents(order))
}

// in registry-arguments.ts
export function registerSigns(
    slots: Signatory[], lookup: (name: string) => void, start: number,
): number {
    let written = start
    for (let offset = slots.length - 1; offset >= start; offset--) {
        const holder = slots[offset]
        lookup(registerCode(signatureOf(holder)))
        written++
    }
    return written
}

// in registry-arguments.ts
export function firstSigned(held: Signatory, room: string): string {
    return room + '/' + registerCode(signatureOf(held))
}

----------[ Good ]----------

function labelFor(details: LabelDetails): string {
    return details.code.trim()
}
```

### converted-argument — in Python

Most callers convert a value the same way for one parameter

```py
----------[ Bad ]----------

# in ledger_dump.py
def dump(entries: list[LedgerEntry]) -> str:
    lines = []
    for entry in entries:
        lines.append(ledger_line(reference=str(entry.number), amount=entry.amount))
    return "\n".join(lines)

# in ledger_posting.py
def post(self, entry: LedgerEntry) -> None:
    self.printed.append(ledger_line(str(entry.number), entry.amount))

# in parcel_weighing.py
def labels(readings: list[ScaleReading]) -> list[str]:
    return [weight_label(float(reading.display)) for reading in readings]

# in parcel_weighing.py
def heaviest_label(readings: list[ScaleReading]) -> str:
    return weight_label(kilos=float(max(readings, key=lambda reading: float(reading.display)).display))

# in tip_cents.py
def ring_up(jar: TipJar, typed: str) -> None:
    jar.add(Cents.parse(typed))

# in tip_cents.py
def ring_up_all(jar: TipJar, entries: list[str]) -> None:
    for typed in entries:
        jar.add(cents=Cents.parse(typed))

----------[ Good ]----------

# in ledger_posting.py
def post_honestly(self, entry: LedgerEntry) -> None:
    self.printed.append(ledger_line_for(entry))

# in ledger_lines.py
def ledger_line_for(entry: LedgerEntry) -> str:
    return f"#{str(entry.number).zfill(6)} {entry.amount}"
```

### converted-argument — in C#

Most callers convert a value the same way for one parameter

```cs
----------[ Bad ]----------

// in Barcodes.cs
public string Top(Parcel parcel) => barcodes.Encode(parcel.Id.ToString());

// in Barcodes.cs
public IEnumerable<string> All(IEnumerable<Parcel> parcels) =>
    parcels.Select(parcel => barcodes.Encode(parcel.Id.ToString()));

----------[ Good ]----------

public string Encode(Guid parcelId) => $"*{parcelId.ToString("N").ToUpperInvariant()}*";
```

### converted-argument — in Go

Most callers convert a value the same way for one parameter

```go
----------[ Bad ]----------

// in freight_arguments.go
func freightLabels(first FreightArgumentCode, second FreightArgumentCode) [2]string {
	one := freightLabel(string(first))
	two := freightLabel(string(second))
	return [2]string{one, two}
}

// in freight_collection.go
func freightCollectionPrice(invoice FreightArgumentCode) string {
	return freightLabel(string(invoice))
}

----------[ Good ]----------

func composeCheckoutLabel(label checkoutLabel) string {
	return label.code + " checkout"
}
```

### divergent-twin — in PHP

Two functions share most of their uncommon steps, but one misses a step the other takes

```php
----------[ Bad ]----------

// in Shop\Audit\EvidencePath
public function under(string $file, string $root): string
{
    $prefix = rtrim($root, '/') . '/';

    if (! str_starts_with($file, $prefix)) {
        return basename($file);
    }

    return substr($file, strlen($prefix));
}

// in Shop\Catalog\SupplierFeed
public function rows(string $path): array
{
    $handle = fopen($path, 'r');

    if (! is_resource($handle)) {
        return [];
    }

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = rtrim((string) $row[0]);
    }

    fclose($handle);

    return $rows;
}

// in Shop\Docs\AssetPath
public function relativeTo(string $file, string $root): string
{
    $prefix = rtrim(realpath($root) ?: $root, '/') . '/';

    if (! str_starts_with($file, $prefix)) {
        return basename($file);
    }

    return substr($file, strlen($prefix));
}

// in Shop\Docs\GuideExport
public function write(string $path, string $guide): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o755, true);
    }

    file_put_contents($path, $guide);
}

// in Shop\Labels\SpoolArchive
public function store(string $path, string $spool): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o755, true);
    }

    $partial = $path . '.' . getmypid();

    file_put_contents($partial, $spool);

    rename($partial, $path);
}

// in Shop\Loyalty\MemberRoster
public function rows(string $path): array
{
    $handle = fopen($path, 'r');

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = rtrim((string) $row[0]);
    }

    fclose($handle);

    return $rows;
}

----------[ Good ]----------

// The same question, asked through the one place that knows a root has to be resolved first.

public function evidenceUnder(string $file, string $root): string
{
    return $this->assets->relativeTo($file, $root);
}
```

### divergent-twin — in TypeScript

Two functions share most of their uncommon steps, but one misses a step the other takes

```ts
----------[ Bad ]----------

// in cash-reconcile.ts
export function reconcileTill(job: Steps): void {
    job.openTill()
    job.countNotes()
    job.matchReceipts()
    job.closeDrawer()
}

// in parcel-readiness.ts
export function dispatchParcel(job: Steps): void {
    job.weigh()
    job.wrap()
    job.label()
    job.stage()
}

// in parcel-readiness.ts
export function dispatchInsuredParcel(job: Steps): void {
    job.weigh()
    job.wrap()
    job.label()
    job.stage()
    job.insure()
}

// in stock-delivery.ts
export function receiveStock(job: Steps): void {
    job.scanCrate()
    job.unpack()
    job.checkQuantity()
    job.updateStock()
}

// in stock-delivery.ts
export function receiveOrderStock(job: Steps): void {
    job.scanCrate()
    job.unpack()
    job.checkQuantity()
    job.updateStock()
    job.notifyBuyer()
}

// in till-audit.ts
export function auditTill(job: Steps): void {
    job.openTill()
    job.countNotes()
    job.matchReceipts()
    job.closeDrawer()
    job.recordDifference()
}

----------[ Good ]----------

export function sendInsuredParcel(job: Steps): void {
    dispatchInsuredParcel(job)
}
```

### divergent-twin — in Python

Two functions share most of their uncommon steps, but one misses a step the other takes

```py
----------[ Bad ]----------

# in photo_archive.py
def archive_photos(folder: str) -> str:
    target = f"{folder}-{time.strftime('%Y%m%d')}.zip"
    with zipfile.ZipFile(target, "w") as archive:
        for root, _, names in os.walk(folder):
            for name in names:
                archive.write(os.path.join(root, name), os.path.relpath(os.path.join(root, name), folder))
    os.chmod(target, 0o640)
    return target

# in photo_archive.py
def archive_thumbnails(folder: str) -> str:
    target = f"{folder}-{time.strftime('%Y%m%d')}.zip"
    with zipfile.ZipFile(target, "w") as archive:
        for root, _, names in os.walk(folder):
            for name in names:
                archive.write(os.path.join(root, name), os.path.relpath(os.path.join(root, name), folder))
    return target

# in price_export.py
def export_prices(folder: str, rows: list[list[str]]) -> None:
    os.makedirs(folder, exist_ok=True)
    shutil.copy2(f"{folder}/prices.csv", f"{folder}/prices.csv.bak")
    with open(f"{folder}/prices.tmp", "w", newline="") as handle:
        csv.writer(handle).writerows(rows)
    os.replace(f"{folder}/prices.tmp", f"{folder}/prices.csv")

# in receipt_signing.py
def signature_matches(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return hmac.compare_digest(expected, claimed)

# in receipt_signing.py
def refund_signature_matches(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return expected == claimed

# in stock_export.py
def export_stock(folder: str, rows: list[list[str]]) -> None:
    os.makedirs(folder, exist_ok=True)
    shutil.copy2(f"{folder}/stock.csv", f"{folder}/stock.csv.bak")
    with open(f"{folder}/stock.tmp", "w", newline="") as handle:
        csv.writer(handle).writerows(rows)
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(f"{folder}/stock.tmp", f"{folder}/stock.csv")

----------[ Good ]----------

def refund_signature_checks(secret: bytes, receipt: bytes, claimed: str) -> bool:
    return signature_matches(secret, receipt, claimed)
```

### divergent-twin — in C#

Two functions share most of their uncommon steps, but one misses a step the other takes

```cs
----------[ Bad ]----------

// in ParcelReadiness.cs
public void DispatchParcel(IParcelReadinessSteps job)
{
    job.Weigh();
    job.Wrap();
    job.Label();
    job.Stage();
}

// in ParcelReadiness.cs
public void DispatchInsuredParcel(IParcelReadinessSteps job)
{
    job.Weigh();
    job.Wrap();
    job.Label();
    job.Stage();
    job.Insure();
}

----------[ Good ]----------

public void SendInsured(IParcelReadinessSteps job)
{
    DispatchInsuredParcel(job);
}
```

### divergent-twin — in Go

Two functions share most of their uncommon steps, but one misses a step the other takes

```go
----------[ Bad ]----------

// in insured_parcel.go
func stageInsuredParcel(work ParcelWork) {
	work.Weigh()
	work.Wrap()
	work.Label()
	work.Stage()
	work.Insure()
}

// in parcel_work.go
func stageParcel(work ParcelWork) {
	work.Weigh()
	work.Wrap()
	work.Label()
	work.Stage()
}

----------[ Good ]----------

func routeInsuredParcel(work ParcelWork) {
	stageInsuredParcel(work)
}
```

### duplicate-function — in PHP

Two functions repeat the same body

```php
----------[ Bad ]----------

// in Shop\Catalog\StockDigest
public function fingerprint(int $base, int $count): string
{
    $total = $base;

    for ($i = 0; $i < $count; $i++) {
        $total += $i * 2;
    }

    return md5((string) $total);
}

// in Shop\Customers\LoyaltyDigest
public function fingerprint(int $base, int $count): string
{
    $total = $base;

    for ($i = 0; $i < $count; $i++) {
        $total += $i * 2;
    }

    return md5((string) $total);
}

// in Shop\Orders\CancellationNote
public function __construct(?string $code)
{
    $this->code = $code ?? Str::uuid()->toString();
}

// in Shop\Orders\DispatchNote
public function __construct(?string $code)
{
    $this->code = $code ?? Str::uuid()->toString();
}

// in Shop\Reporting\SalesDigest
public function fingerprint(int $base, int $count): string
{
    $total = $base;

    for ($i = 0; $i < $count; $i++) {
        $total += $i * 2;
    }

    return md5((string) $total);
}

----------[ Good ]----------

public static function of(int $base, int $count): string
{
    $steps = array_map(static fn (int $i): int => $i * 2, range(0, max(0, $count - 1)));

    return md5((string) ($base + array_sum($steps)));
}
```

### duplicate-function — in TypeScript

Two functions repeat the same body

```ts
----------[ Bad ]----------

// in packing-slip.ts
rows(lines: Line[]): string[] {
    const rows: string[] = []
    for (const line of lines) {
        if (line.quantity <= 0) {
            continue
        }
        rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
    }
    return rows
}

// in packing-slip.ts
lineItems(lines: Line[]): string[] {
    const rows: string[] = []
    for (const line of lines) {
        if (line.quantity <= 0) {
            continue
        }
        rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
    }
    return rows
}

// in stock-lookup.ts
export async function loadStockLevels(sku: string): Promise<number[]> {
    const response = await fetch(`/api/stock/${sku}`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const levels: StockLevel[] = await response.json()
    return levels.map((level) => level.available)
}

// in NewsletterSignup.vue
async function subscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}

// in NewsletterSignup.vue
async function resubscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}

// in RestockNotice.vue
const fetchAvailability = async (sku: string): Promise<number[]> => {
    const response = await fetch(`/api/stock/${sku}`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const levels: StockLevel[] = await response.json()
    return levels.map((level) => level.available)
}

----------[ Good ]----------

// in order-lines.ts
export function describeLines(lines: Line[]): string[] {
    return lines
        .filter((line) => line.quantity > 0)
        .map((line) => `${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
}

// in order-lines.ts
rows(lines: Line[]): string[] {
    return describeLines(lines)
}

// in order-lines.ts
lineItems(lines: Line[]): string[] {
    return describeLines(lines)
}
```

### duplicate-function — in Vue

Two functions repeat the same body

```vue
----------[ Bad ]----------

<!-- in components/NewsletterSignup.vue -->
<script setup lang="ts">
// Two handlers in one component with one body: subscribing and re-subscribing post the same
// form and recover from the same failure, so the second is a copy of the first.
import { ref } from 'vue'

const email = ref('')
const failed = ref(false)

async function subscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}

async function resubscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}
</script>

<template>
    <form @submit.prevent="subscribe">
        <input v-model="email" type="email" />
        <button type="button" @click="resubscribe">Subscribe again</button>
    </form>
</template>

----------[ Good ]----------

<!-- in components/NewsletterForm.vue -->
<script setup lang="ts">
// The FIX: subscribing again is the same request, so there is one function and both buttons call it.
import { ref } from 'vue'

const address = ref('')
const rejected = ref(false)

async function subscribe(): Promise<void> {
    rejected.value = false
    await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: address.value }) })
        .catch(() => { rejected.value = true })
}
</script>

<template>
    <form @submit.prevent="subscribe">
        <input v-model="address" type="email" />
        <button type="button" @click="subscribe">Subscribe again</button>
    </form>
</template>
```

### duplicate-function — in Python

Two functions repeat the same body

```py
----------[ Bad ]----------

# in cli.py
def load_config(path: Path) -> dict:
    if not path.is_file():
        raise SettingsMissing(str(path))
    settings = json.loads(path.read_text())
    return {key.lower(): value for key, value in settings.items()}

# in documents.py
def rows(self, lines: list[Line]) -> list[str]:
    rows = []
    for line in lines:
        if line.quantity <= 0:
            continue
        rows.append(f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}")
    return rows

# in documents.py
def line_items(self, lines: list[Line]) -> list[str]:
    rows = []
    for line in lines:
        if line.quantity <= 0:
            continue
        rows.append(f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}")
    return rows

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in settings.py
def read_settings(path: Path) -> dict:
    if not path.is_file():
        raise SettingsMissing(str(path))
    settings = json.loads(path.read_text())
    return {key.lower(): value for key, value in settings.items()}

----------[ Good ]----------

# in layout.py
def describe_lines(lines: list[Line]) -> list[str]:
    return [f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}" for line in lines if line.quantity > 0]

# in layout.py
def rows(self, lines: list[Line]) -> list[str]:
    return describe_lines(lines)

# in layout.py
def line_items(self, lines: list[Line]) -> list[str]:
    return describe_lines(lines)
```

### duplicate-function — in Go

Two functions repeat the same body

```go
----------[ Bad ]----------

// in label_archive.go
func archivePrice(sizes []int) int {
	total := 0
	for _, size := range sizes {
		if size > 20 {
			total += 25
			continue
		}
		total += 12
	}
	return total
}

// in label_purge.go
func purgePrice(sizes []int) int {
	total := 0
	for _, size := range sizes {
		if size > 20 {
			total += 25
			continue
		}
		total += 12
	}
	return total
}

// in label_reprint.go
func reprintPrice(sizes []int) int {
	total := 0
	for _, size := range sizes {
		if size > 20 {
			total += 25
			continue
		}
		total += 12
	}
	return total
}

----------[ Good ]----------

func sharedArchivePrice(sizes []int) int {
	return archivePrice(sizes)
}
```

### list-mirrors-folder — in PHP

A hand-written class list repeats every concrete class of one type in its folders

```php
----------[ Bad ]----------

public function daily(): array
{
    return [
        LowStockRule::class,
        HighRefundRule::class,
        SlowDispatchRule::class,
        ChargebackRule::class,
        EmptyBasketRule::class,
        PriceDropRule::class,
    ];
}

----------[ Good ]----------

// Hands the till every step its Steps folder declares, so a new step needs no second edit.

final class TillStepFinder
{
    public function wire(TillSteps $steps): void
    {
        $steps->addAll(array_map(
            fn (string $file): string => __NAMESPACE__.'\\Steps\\'.basename($file, '.php'),
            glob(__DIR__.'/Steps/*.php'),
        ));
    }
}
```

### list-mirrors-folder — in TypeScript

A hand-written class list repeats every concrete class of one type in its folders

```ts
----------[ Bad ]----------

export function parcelNoticeTypes(): (typeof ParcelNotice)[] {
    return [DelayedNotice, DeliveredNotice, PickupNotice, ReturnNotice, DispatchNotice]
}

----------[ Good ]----------

export function registeredParcelNotices(): (typeof ParcelNotice)[] {
    return notices
}
```

### list-mirrors-folder — in Python

A hand-written class list repeats every concrete class of one type in its folders

```py
----------[ Bad ]----------

def all_types() -> list[type[CouponSource]]:
    return [CounterCouponSource, NewsletterCouponSource, AppCouponSource, ReferralCouponSource, CatalogueCouponSource]

----------[ Good ]----------

def starter_types() -> list[type[CouponSource]]:
    return [CounterCouponSource, NewsletterCouponSource]
```

### list-mirrors-folder — in C#

A hand-written class list repeats every concrete class of one type in its folders

```cs
----------[ Bad ]----------

public static Type[] All()
{
    return [typeof(CounterCouponSource), typeof(NewsletterCouponSource), typeof(AppCouponSource), typeof(ReferralCouponSource), typeof(CatalogueCouponSource)];
}

----------[ Good ]----------

public static Type[] ForKiosk()
{
    return [typeof(CounterCouponSource), typeof(NewsletterCouponSource)];
}
```

### near-duplicate-function — in PHP

Two functions run the same code with different names or data

```php
----------[ Bad ]----------

// in Shop\Pricing\TierScorer
public function scoreFrom(int $seed): int
{
    $score = $seed;

    foreach ($this->entries as $weight) {
        if ($weight > 0) {
            $score += $weight * 2;
        }
    }

    return $score;
}

// in Shop\Reporting\WeightAggregator
public function accumulateFrom(int $start): int
{
    $total = $start;

    foreach ($this->entries as $row) {
        if ($row > 0) {
            $total += $row * 5;
        }
    }

    return $total;
}

// in Shop\Shipping\RouteCostEstimator
public function estimateFrom(int $surcharge): int
{
    $cost = $surcharge;

    foreach ($this->entries as $leg) {
        if ($leg > 0) {
            $cost += $leg * 3;
        }
    }

    return $cost;
}

----------[ Good ]----------

// The duplicated scorers collapsed into one parameterised pass — the per-entry
// weight is an argument, so there is no rhyming twin to extract.

public function scoreFrom(int $start, int $weight): int
{
    return array_reduce(
        array_filter($this->entries, static fn (int $row): bool => $row > 0),
        static fn (int $total, int $row): int => $total + $row * $weight,
        $start,
    );
}
```

### near-duplicate-function — in TypeScript

Two functions run the same code with different names or data

```ts
----------[ Bad ]----------

// in carrier-rates.ts
quote(parcel: Parcel): number {
    let cents = 695 + Math.ceil(parcel.kilos) * 120
    if (parcel.oversized) {
        cents += 450
    }
    if (parcel.kilos > 20) {
        cents = Math.round(cents * 1.15)
    }
    return cents
}

// in carrier-rates.ts
quote(parcel: Parcel): number {
    let price = 950 + Math.ceil(parcel.kilos) * 85
    if (parcel.oversized) {
        price += 700
    }
    if (parcel.kilos > 30) {
        price = Math.round(price * 1.1)
    }
    return price
}

// in map-loaders.ts
async function loadMaps(): Promise<unknown> {
    if (typeof window === 'undefined') {
        throw new Error('Maps load only in the browser')
    }
    ensureOptions()
    return await importLibrary('maps')
}

// in map-loaders.ts
async function loadPlaces(): Promise<unknown> {
    if (typeof window === 'undefined') {
        throw new Error('Maps load only in the browser')
    }
    ensureOptions()
    return await importLibrary('places')
}

// in map-loaders.ts
async function loadMarker(): Promise<unknown> {
    if (typeof window === 'undefined') {
        throw new Error('Maps load only in the browser')
    }
    ensureOptions()
    return await importLibrary('marker')
}

// in review-feed.ts
export async function loadReviews(productId: number): Promise<number[]> {
    const response = await fetch(`/api/products/${productId}/reviews`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const reviews: Entry[] = await response.json()
    const shown = reviews.filter((review) => review.published)
    return shown.map((review) => review.id)
}

// in PickListActions.vue
async function release(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/release`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
}

// in PickListActions.vue
async function pause(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/pause`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
}

// in QuestionFeed.vue
const loadQuestions = async (productId: number): Promise<number[]> => {
    const result = await fetch(`/api/products/${productId}/questions`)
    if (!result.ok) {
        throw new Error(result.statusText)
    }
    const questions: Entry[] = await result.json()
    const answered = questions.filter((question) => question.published)
    return answered.map((question) => question.id)
}

----------[ Good ]----------

// in parcel-pricing.ts
export function priceParcel(parcel: Parcel, tariff: Tariff): number {
    const weighed = tariff.base + Math.ceil(parcel.kilos) * tariff.perKilo
    const sized = parcel.oversized ? weighed + tariff.oversizeFee : weighed
    return parcel.kilos > tariff.heavyFrom ? Math.round(sized * tariff.heavyFactor) : sized
}

// in parcel-pricing.ts
quote(parcel: Parcel): number {
    return priceParcel(parcel, { base: 695, perKilo: 120, oversizeFee: 450, heavyFrom: 20, heavyFactor: 1.15 })
}

// in parcel-pricing.ts
quote(parcel: Parcel): number {
    return priceParcel(parcel, { base: 950, perKilo: 85, oversizeFee: 700, heavyFrom: 30, heavyFactor: 1.1 })
}
```

### near-duplicate-function — in Vue

Two functions run the same code with different names or data

```vue
----------[ Bad ]----------

// in carrier-rates.ts
quote(parcel: Parcel): number {
    let cents = 695 + Math.ceil(parcel.kilos) * 120
    if (parcel.oversized) {
        cents += 450
    }
    if (parcel.kilos > 20) {
        cents = Math.round(cents * 1.15)
    }
    return cents
}

// in carrier-rates.ts
quote(parcel: Parcel): number {
    let price = 950 + Math.ceil(parcel.kilos) * 85
    if (parcel.oversized) {
        price += 700
    }
    if (parcel.kilos > 30) {
        price = Math.round(price * 1.1)
    }
    return price
}

// in map-loaders.ts
async function loadMaps(): Promise<unknown> {
    if (typeof window === 'undefined') {
        throw new Error('Maps load only in the browser')
    }
    ensureOptions()
    return await importLibrary('maps')
}

// in map-loaders.ts
async function loadPlaces(): Promise<unknown> {
    if (typeof window === 'undefined') {
        throw new Error('Maps load only in the browser')
    }
    ensureOptions()
    return await importLibrary('places')
}

// in map-loaders.ts
async function loadMarker(): Promise<unknown> {
    if (typeof window === 'undefined') {
        throw new Error('Maps load only in the browser')
    }
    ensureOptions()
    return await importLibrary('marker')
}

// in review-feed.ts
export async function loadReviews(productId: number): Promise<number[]> {
    const response = await fetch(`/api/products/${productId}/reviews`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const reviews: Entry[] = await response.json()
    const shown = reviews.filter((review) => review.published)
    return shown.map((review) => review.id)
}

// in PickListActions.vue
async function release(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/release`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
}

// in PickListActions.vue
async function pause(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/pause`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
}

// in QuestionFeed.vue
const loadQuestions = async (productId: number): Promise<number[]> => {
    const result = await fetch(`/api/products/${productId}/questions`)
    if (!result.ok) {
        throw new Error(result.statusText)
    }
    const questions: Entry[] = await result.json()
    const answered = questions.filter((question) => question.published)
    return answered.map((question) => question.id)
}

----------[ Good ]----------

async function post(action: string): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/${action}`, { method: 'POST' })
    if (response.ok) {
        emit('done', action)
    }
}
```

### near-duplicate-function — in Python

Two functions run the same code with different names or data

```py
----------[ Bad ]----------

# in alerts.py
def register(bus) -> None:
    bus.intercept(WatchKeyword("alerts", whole_words=True))
    bus.handle(RepeatStanding("alerts", every=25))
    bus.handle(MentionInChat("alerts", limit=3))
    bus.log.info("registered %s", "alerts")

# in digests.py
def register(bus) -> None:
    bus.intercept(WatchKeyword("digests", whole_words=True))
    bus.handle(RepeatStanding("digests", every=50))
    bus.handle(MentionInChat("digests", limit=1))
    bus.log.info("registered %s", "digests")

# in encoders.py
def encode_text(text: str) -> tuple[dict[str, str], ByteStream]:
    body = text.encode("utf-8")
    length = str(len(body))
    kind = "text/plain; charset=utf-8"
    headers = {"Content-Length": length, "Content-Type": kind}
    return headers, ByteStream(body)

# in encoders.py
def encode_html(html: str) -> tuple[dict[str, str], ByteStream]:
    body = html.encode("utf-8")
    length = str(len(body))
    kind = "text/html; charset=utf-8"
    headers = {"Content-Length": length, "Content-Type": kind}
    return headers, ByteStream(body)

# in holds.py
def hold_order(warehouse, number: str) -> dict:
    found = warehouse.locate(number)
    queued = warehouse.queue(number, "Put on hold", site=found["site"], action=HOLD)
    return {key: value for key, value in queued.items() if key != "line"} | {"queued": True}

# in holds.py
def release_order(warehouse, number: str) -> dict:
    found = warehouse.locate(number)
    queued = warehouse.queue(number, "Release to picking", site=found["site"], action=RELEASE)
    return {key: value for key, value in queued.items() if key != "line"} | {"queued": True}

----------[ Good ]----------

# in encoding.py
def encode(text: str, kind: str) -> tuple[dict[str, str], ByteStream]:
    body = text.encode("utf-8")
    headers = {"Content-Length": str(len(body)), "Content-Type": f"{kind}; charset=utf-8"}
    return headers, ByteStream(body)

# in encoding.py
def encode_text(text: str) -> tuple[dict[str, str], ByteStream]:
    return encode(text, "text/plain")

# in encoding.py
def encode_html(html: str) -> tuple[dict[str, str], ByteStream]:
    return encode(html, "text/html")
```

### near-duplicate-function — in Go

Two functions run the same code with different names or data

```go
----------[ Bad ]----------

// in label_archive.go
func archiveScore(labels []string) int {
	score := 15
	for _, label := range labels {
		if len(label) > 30 {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}

// in label_purge.go
func purgeScore(labels []string) int {
	score := 7
	for _, label := range labels {
		if len(label) > 20 {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}

// in label_reprint.go
func reprintScore(labels []string) int {
	score := 23
	for _, label := range labels {
		if len(label) > 40 {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}

----------[ Good ]----------

func archiveScoreFor(labels []string, initial, longLabelSize int) int {
	score := initial
	for _, label := range labels {
		if len(label) > longLabelSize {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}
```

### repeated-folder-path — in PHP

One folder is spelled out at three or more sites across separate scopes

```php
----------[ Bad ]----------

public function write(array $rows, string $day): string
{
    $file = storage_path('exports/payouts/' . $day . '.csv');
    $handle = fopen($file, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $file;
}

----------[ Good ]----------

// in Shop\Refunds\RefundFolder
public function file(string $name): string
{
    return storage_path('refunds/receipts/' . $name);
}

// in Shop\Refunds\RefundReceipts
public function store(string $refund, string $pdf): void
{
    file_put_contents($this->folder->file($refund . '.pdf'), $pdf);
}

// in Shop\Refunds\RefundReceipts
public function exists(string $refund): bool
{
    return is_file($this->folder->file($refund . '.pdf'));
}

// in Shop\Refunds\RefundReceipts
public function remove(string $refund): bool
{
    return unlink($this->folder->file($refund . '.pdf'));
}
```

### repeated-folder-path — in TypeScript

One folder is spelled out at three or more sites across separate scopes

```ts
----------[ Bad ]----------

export function payoutArchive(batch: string): { batch: string; output: string } {
    const output = join(__dirname, 'fixtures', 'payout-csv', 'archive.csv')

    return { batch, output }
}

----------[ Good ]----------

export function namedPayoutArchive(batch: string): { batch: string; output: string } {
    return { batch, output: join(payoutCsvFolder, 'archive.csv') }
}
```

### repeated-folder-path — in Python

One folder is spelled out at three or more sites across separate scopes

```py
----------[ Bad ]----------

def archive_label(save: Callable[[str, bytes], None], printed: bytes) -> None:
    save(os.path.join("warehouse", "saved-labels", "archive.pdf"), printed)

----------[ Good ]----------

def archive_saved_label(save: Callable[[str, bytes], None], printed: bytes) -> None:
    save(os.path.join(SAVED_LABELS, "archive.pdf"), printed)
```

### repeated-folder-path — in C#

One folder is spelled out at three or more sites across separate scopes

```cs
----------[ Bad ]----------

public void Write(byte[] printed)
{
    save(Path.Combine("warehouse", "saved-labels", "archive.pdf"), printed);
}

----------[ Good ]----------

public void WriteToArchive(byte[] printed)
{
    save(Path.Combine(SavedLabels, "archive.pdf"), printed);
}
```

### repeated-folder-path — in Go

One folder is spelled out at three or more sites across separate scopes

```go
----------[ Bad ]----------

func archiveLabel(save func(string, []byte) error, printed []byte) error {
	if len(printed) == 0 {
		return nil
	}
	name := filepath.Join("warehouse", "saved-labels", "archive.pdf")
	return save(name, printed)
}

----------[ Good ]----------

func archiveSavedLabel(save func(string, []byte) error, printed []byte) error {
	return save(filepath.Join(savedLabels, "archive.pdf"), printed)
}
```

### repeated-guard — in PHP

A compound condition about an object is written out at more than one site

```php
----------[ Bad ]----------

// in Shop\Domain\AccessPolicy
public function allow($user, $account): bool
{
    return $user->active && $account->verified;
}

// in Shop\Domain\AccessPolicy
public function audit($user, $account): string
{
    $live = $user->active;
    $ok = $account->verified;

    return $live && $ok ? 'granted' : 'denied';
}

----------[ Good ]----------

// in Shop\Domain\AccessPolicy
// The guard promoted to a NAME: `isLocked()` spells `$user->suspended && $account->frozen` once and
// every site asks the predicate, so the condition has one home and moves in one place.

public function review($user, $account): string
{
    return $this->isLocked($user, $account) ? 'locked' : 'open';
}

// in Shop\Domain\AccessPolicy
private function isLocked($user, $account): bool
{
    return $user->suspended && $account->frozen;
}
```

### repeated-guard — in TypeScript

A compound condition about an object is written out at more than one site

```ts
----------[ Bad ]----------

// in payout-builder.ts
export function payoutReady(payout: { approved: boolean; cleared: boolean }): boolean {
    return payout.approved && payout.cleared
}

// in payout-cleanup.ts
export function purgeApproved(
    payout: { approved: boolean; cleared: boolean }, batch: string, audit: Map<string, number>,
): void {
    if (payout.approved && payout.cleared) {
        const previous = audit.get(batch) ?? 0
        audit.set(batch, previous + 1)
    }
}

// in payout-retry.ts
export function retryApproved(payout: { approved: boolean; cleared: boolean }, context: URL): URL | undefined {
    if (payout.cleared && payout.approved) {
        const address = new URL('reissue', context)
        address.searchParams.set('reason', 'queue timeout')
        return address
    }
    return undefined
}

----------[ Good ]----------

export function archiveReady(payout: { approved: boolean; cleared: boolean }): boolean {
    return canPay(payout)
}
```

### repeated-guard — in Python

A compound condition about an object is written out at more than one site

```py
----------[ Bad ]----------

# in loyalty_points.py
def redeem(member: Member, cost: int) -> int:
    if member.points >= cost and not member.frozen:
        return member.points - cost
    raise ValueError(cost)

# in loyalty_points.py
def preview(member: Member, cost: int) -> str:
    enough = member.points >= cost
    return "redeemable" if not member.frozen and enough else "locked"

----------[ Good ]----------

class Account:
    def __init__(self, points: int, frozen: bool) -> None:
        self.points = points
        self.frozen = frozen

    def can_redeem(self, cost: int) -> bool:
        return self.points >= cost and not self.frozen

    def redeem(self, cost: int) -> int:
        if self.can_redeem(cost):
            return self.points - cost
        raise ValueError(cost)
```

### repeated-guard — in C#

A compound condition about an object is written out at more than one site

```cs
----------[ Bad ]----------

// in Promises.cs
public static bool CanPromiseToday(Dispatchable order) => !order.OnHold && order.Paid && order.Lines > 0;

// in Dispatch.cs
public static string Status(Dispatchable order)
{
    if (order.Paid && !order.OnHold && order.Lines > 0)
    {
        return "pick";
    }

    return "wait";
}

----------[ Good ]----------

// in Dispatch.cs
public bool IsReady => Paid && !OnHold && Lines > 0;

// in Dispatch.cs
public static string Label(Dispatchable order) => order.IsReady ? "pick" : "wait";
```

### repeated-guard — in Go

A compound condition about an object is written out at more than one site

```go
----------[ Bad ]----------

// in label_archive.go
func stockedPrinter(printer LabelPrinter) bool {
	return printer.Ready && printer.Paper > 0
}

// in label_purge.go
func printPurgeReceipt(printers map[string]LabelPrinter, log func(string)) {
	for aisle, printer := range printers {
		if printer.Ready && printer.Paper > 0 {
			log(aisle)
		}
	}
}

// in label_reprint.go
func retryPrint(printer LabelPrinter, failed <-chan string, print func(string)) {
	name, open := <-failed
	if !open {
		return
	}
	if printer.Paper > 0 && printer.Ready {
		print(name)
	}
}

----------[ Good ]----------

func archiveOnStockedPrinter(printer LabelPrinter, archive func()) {
	if printer.canPrint() {
		archive()
	}
}
```

### repeated-type-guard — in PHP

The same compound type check is written out at more than one site

```php
----------[ Bad ]----------

// in Shop\Domain\TokenScanner
public function opens($t): bool
{
    return $t instanceof Bracket && $t->pair instanceof Bracket;
}

// in Shop\Domain\TokenScanner
public function pairs(array $tokens): int
{
    $count = 0;

    foreach ($tokens as $t) {
        $count += $t instanceof Bracket && $t->pair instanceof Bracket ? 1 : 0;
    }

    return $count;
}

----------[ Good ]----------

// in Shop\Domain\TokenScanner
// The narrowing promoted to a NAME: `isBalancedBrace()` holds `$t instanceof Brace &&
// $t->close instanceof Brace` once, and every site asks the predicate instead of copying the chain.

public function balanced(array $tokens): int
{
    return count(array_filter($tokens, $this->isBalancedBrace(...)));
}

// in Shop\Domain\TokenScanner
private function isBalancedBrace($t): bool
{
    return $t instanceof Brace && $t->close instanceof Brace;
}
```

### repeated-type-guard — in TypeScript

The same compound type check is written out at more than one site

```ts
----------[ Bad ]----------

// in payout-builder.ts
export function payoutDate(cents: unknown, issued: unknown): Date | undefined {
    if (typeof cents === 'number' && issued instanceof Date) {
        return issued
    }

    return undefined
}

// in payout-cleanup.ts
export function purgeDate(cents: unknown, issued: unknown, output: (amount: number, key: string) => void): number {
    if (typeof cents === 'number' && issued instanceof Date) {
        output(cents / 100, issued.toISOString())
        return cents
    }
    return 0
}

// in payout-retry.ts
export function retryDate(cents: unknown, issued: unknown): { next: number; balance: bigint } | undefined {
    if (issued instanceof Date && typeof cents === 'number') {
        const next = issued.getTime() + 3_600_000
        return { next, balance: BigInt(Math.round(cents)) }
    }
    return undefined
}

----------[ Good ]----------

export function validPayoutDate(cents: unknown, issued: unknown): Date | undefined {
    const dateAndAmount = typeof cents === 'number' && issued instanceof Date
    if (dateAndAmount) {
        return issued
    }

    return undefined
}
```

### repeated-type-guard — in Python

The same compound type check is written out at more than one site

```py
----------[ Bad ]----------

# in payment_events.py
def challenge_url(event: object) -> str:
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        return event.step.url
    return ""

# in payment_events.py
def notify_customer(event: object, outbox: list[str]) -> None:
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        outbox.append(event.step.url)

# in refund_events.py
def refund_cents(event: object, limit_cents: int) -> int:
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        return 0
    return limit_cents

----------[ Good ]----------

# in payment_events.py
@property
def is_challenged(self) -> bool:
    return isinstance(self.step, Challenge)

# in payment_events.py
def challenge_link(event: object) -> str:
    if isinstance(event, CardPayment) and event.is_challenged:
        return str(event.step)
    return ""
```

### repeated-type-guard — in C#

The same compound type check is written out at more than one site

```cs
----------[ Bad ]----------

// in Damage.cs
public static int ToInspect(IEnumerable<Package> arrived, int alreadyChecked)
{
    var total = 0;

    foreach (var package in arrived)
    {
        total += package is Crate crate && crate.Lid is ScrewLid ? 1 : 0;
    }

    return total - alreadyChecked;
}

// in Crates.cs
public static string Instructions(Package package)
{
    if (package is Crate crate && crate.Lid is ScrewLid)
    {
        return "use the drill";
    }

    return "lift by hand";
}

// in Palletising.cs
public int SlotsFor(Package package)
{
    var needsRoom = package is Crate crate && crate.Lid is ScrewLid;
    var slots = needsRoom ? 2 : 1;

    return Math.Min(slots, slotsFree);
}

----------[ Good ]----------

// in Crates.cs
public bool IsScrewedCrate => this is Crate crate && crate.Lid is ScrewLid;

// in Crates.cs
public static string Handling(Package package) => package.IsScrewedCrate ? "use the drill" : "lift by hand";
```

### unnamed-vocabulary-literal — in PHP

A string literal fills a parameter that already has a named constant for that value

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

### unnamed-vocabulary-literal — in TypeScript

A string literal fills a parameter that already has a named constant for that value

```ts
----------[ Bad ]----------

export function sendByLiteral(send: (word: string) => void): void {
    applyChannel('counter', send)
}

----------[ Good ]----------

export function sendByName(send: (word: string) => void): void {
    applyChannel(CouponChannel.Counter, send)
}
```

### unnamed-vocabulary-literal — in Python

A string literal fills a parameter that already has a named constant for that value

```py
----------[ Bad ]----------

def field_end(self) -> None:
    self.expect(",")

----------[ Good ]----------

# The FIX: the comma is the vocabulary's `COMMA`, so the reader finds it where every token is named.
def cell_end(self) -> None:
    self.expect(Punctuation.COMMA)
```

### unnamed-vocabulary-literal — in C#

A string literal fills a parameter that already has a named constant for that value

```cs
----------[ Bad ]----------

public int EndOfMonth() => runner.Run("monthly");

----------[ Good ]----------

public int Close() => runner.Run(ReportKind.Monthly);
```

### unnamed-vocabulary-literal — in Go

A string literal fills a parameter that already has a named constant for that value

```go
----------[ Bad ]----------

func saveLabelByLiteral(send func(LabelMode)) {
	saveLabel("archive", send)
}

----------[ Good ]----------

func saveLabelByName(send func(LabelMode)) {
	saveLabel(archiveMode, send)
}
```
