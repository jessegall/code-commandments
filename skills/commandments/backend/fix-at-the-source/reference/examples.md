# Fix at the source — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### constructor-side-effect

a constructor that performs a SIDE EFFECT on a collaborator — the result thrown away, so merely building the object changes the world

```php
----------[ Bad ]----------

// Warms the export the moment anyone builds one, so merely HAVING a LedgerExport costs a request
// — and nothing in the code that constructed it asked for that.

final class LedgerExport
{
    public function __construct(private readonly HttpClient $client, private readonly string $period)
    {
        $this->client->get("/ledger/{$period}/warm");
    }

    public function body(): string
    {
        return $this->client->get("/ledger/{$this->period}");
    }
}

----------[ Good ]----------

// The same export, built for free. It holds the client and does the fetching when someone asks
// for rows, which is the moment the caller chose.

final class LazyLedgerExport
{
    public function __construct(private readonly HttpClient $client, private readonly string $period) {}

    public function body(): string
    {
        return $this->client->get("/ledger/{$this->period}");
    }
}
```

### divergent-twin

Two methods do one job — the same rare verbs, in different words — and one of them does strictly less of it, which is what a change looks like when it landed in only one of the two places that should have been one

```php
----------[ Bad ]----------

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

// in Shop\Docs\GuideExport
public function write(string $path, string $guide): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o755, true);
    }

    file_put_contents($path, $guide);
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

// in Shop\Audit\EvidencePath
public function under(string $file, string $root): string
{
    $prefix = rtrim($root, '/') . '/';

    if (! str_starts_with($file, $prefix)) {
        return basename($file);
    }

    return substr($file, strlen($prefix));
}

----------[ Good ]----------

// The same roster, read through the one place that knows how a drop is opened here.

public function members(string $path): array
{
    return $this->feed->rows($path);
}
```

### duplicate-function

Copy-pasted code — two+ functions with an identical AST (formatting/comments aside)

```php
----------[ Bad ]----------

// in Shop\Customers\LoyaltyDigest
public function fingerprint(int $base, int $count): string
{
    $total = $base;

    for ($i = 0; $i < $count; $i++) {
        $total += $i * 2;
    }

    return md5((string) $total);
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

// in Shop\Catalog\StockDigest
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

### manufactured-fake-fill

`?? <empty literal>` filling a required slot (manufactured fake)

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

### mutable-static-state

a write to a static property — a global wearing a namespace, where whoever writes last wins and execution order becomes load-bearing

```php
----------[ Bad ]----------

public static function record(string $at, string $message): void
{
    self::$entries[] = "[{$at}] {$message}";

    if (count(self::$entries) > self::KEEP) {
        self::$entries = array_slice(self::$entries, -self::KEEP);
    }
}

----------[ Good ]----------

// in Shop\Pricing\OwnedRateTable
public function __construct(private readonly array $table) {}

// in Shop\Pricing\OwnedRateTable
public function for(string $region): float
{
    return $this->table[$region] ?? 1.0;
}
```

### near-duplicate-function

Redundant methods — two+ functions with the same SHAPE differing only in names/literals (type-2 clone)

```php
----------[ Bad ]----------

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
