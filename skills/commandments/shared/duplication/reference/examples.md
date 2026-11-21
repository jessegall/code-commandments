# Duplication: give repeated code one owner — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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

// in CashReconcile.cs
public void ReconcileTill(ICashReconcileSteps job)
{
    job.OpenTill();
    job.CountNotes();
    job.MatchReceipts();
    job.CloseDrawer();
}

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

// in StockDelivery.cs
public void ReceiveStock(IStockDeliverySteps job)
{
    job.ScanCrate();
    job.Unpack();
    job.CheckQuantity();
    job.UpdateStock();
}

// in StockDelivery.cs
public void ReceiveOrderStock(IStockDeliverySteps job)
{
    job.ScanCrate();
    job.Unpack();
    job.CheckQuantity();
    job.UpdateStock();
    job.NotifyBuyer();
}

// in TillAudit.cs
public void AuditTill(ICashReconcileSteps job)
{
    job.OpenTill();
    job.CountNotes();
    job.MatchReceipts();
    job.CloseDrawer();
    job.RecordDifference();
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

// in drawer_reconcile.go
func balanceTill(work DrawerWork) {
	work.OpenTill()
	work.CountNotes()
	work.MatchReceipts()
	work.CloseDrawer()
}

// in drawer_reconcile.go
func auditDrawer(work DrawerWork) {
	work.OpenTill()
	work.CountNotes()
	work.MatchReceipts()
	work.CloseDrawer()
	work.RecordDifference()
}

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

// in stock_receive.go
func receiveStock(work StockWork) {
	work.ScanCrate()
	work.Unpack()
	work.CheckQuantity()
	work.UpdateStock()
}

// in stock_receive.go
func receiveOrderedStock(work StockWork) {
	work.ScanCrate()
	work.Unpack()
	work.CheckQuantity()
	work.UpdateStock()
	work.NotifyBuyer()
}

----------[ Good ]----------

func routeInsuredParcel(work ParcelWork) {
	stageInsuredParcel(work)
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
