# Documentation — concise, present-tense, rare — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### archaeology-comment

History/archaeology comments ("formerly / used to be / refactored / no longer an X / was extracted")

```php
----------[ Bad ]----------

/**
 * @param  array<string, mixed>  $event
 */
public function handle(array $event): void
{
    // formerly lived inline in the StripeController; was extracted here
    $type = $event['type'];

    $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
}

----------[ Good ]----------

/**
 * @param  array<string, mixed>  $event
 */
public function handleRefund(array $event): void
{
    // a refund carries no id of its own, so the charge reference identifies the row
    $this->record('refund', $this->reference($event));
}
```

### bloated-docblock

Multi-paragraph class docblock (class too big)

```php
----------[ Bad ]----------

/**
 * This class is responsible for importing legacy orders from the old system.
 * It was originally extracted from the monolith during the 2023 migration and
 * has been refactored several times since. It reads the legacy CSV export, maps
 * each row to a customer, and creates the order. Previously this logic lived in
 * the OrderController but was moved here to keep controllers thin.
 *
 * TODO: remove once the legacy importer is fully decommissioned.
 */
final class LegacyOrderImporter
{
    // previously this returned an array, now it returns a Customer or null
    public function findCustomer(string $email): ?Customer
    {
        // loop over all customers and find the matching one
        return Customer::query()->where('email', $email)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
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

    /**
     * The FIX: a row with no email is an absence at the SOURCE, so the boundary names the failure and
     * throws. The `?? ''` version looked up "the customer whose email is the empty string" and carried
     * that fake all the way to the import — the throw stops the row here, where the truth is known.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function importStrictly(array $rows): void
    {
        foreach ($rows as $row) {
            $email = $row['email'] ?? throw new MissingImportEmail();

            $this->findCustomer($email)?->markImported();
        }
    }

    public function emailKnown(string $email): bool
    {
        return $this->findCustomer($email)?->exists ?? false;
    }
}

----------[ Good ]----------

/**
 * Renders the monthly sales spreadsheet finance imports by hand.
 */
final class MonthlySalesReport
{
    public function __construct(private readonly SalesGrouping $grouping) {}

    public function render(int $month): string
    {
        return $this->grouping->for($month) . "-report.xlsx";
    }
}
```

### ceremony-docblock

Docblock that only restates the typed signature (`@param Type $x`, no description)

```php
----------[ Bad ]----------

/**
 * @param  int  $sequence
 * @return  string
 */
public function format(int $sequence): string
{
    return sprintf('%s-%06d', $this->prefix, $sequence);
}

----------[ Good ]----------

/**
 * Renders the number finance quotes on a remittance — the sequence is zero-padded to six digits
 * so invoices sort lexically in the ledger export.
 */
public function formatForRemittance(int $sequence): string
{
    return sprintf('%s-%06d', $this->prefix, $sequence);
}
```

### dangling-doc-reference

A docblock `{@see}`/`{@link}` cross-references a FIRST-PARTY class that does not exist in the codebase — documentation pointing at a name that was renamed or removed, never at what the code actually is

```php
----------[ Bad ]----------

/**
 * Picks the cheapest carrier. Rates come from {@see \Shop\Shipping\RemovedRateBook} — deleted, so the
 * link is stale.
 */
public function cheapest(string $zone, float $weight): string
{
    return $weight > 10.0 ? "freight:{$zone}" : "parcel:{$zone}";
}

----------[ Good ]----------

/**
 * Picks the cheapest carrier for a heavy parcel. Rates come from
 * {@see \Shop\Shipping\ShippingRateRegistry} — the class the rate book became, so the reference
 * resolves.
 */
public function cheapestFreight(string $zone): string
{
    return "freight:{$zone}";
}
```

### inline-docblock

A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content

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

/**
 * A node in a test log tree — it holds its own children, so a failure is knowledge it could answer.
 */
final class LogEntry
{
    public string $level = 'info';

    /**
     * @var list<LogEntry>
     */
    public array $children = [];

    public function isError(): bool
    {
        return $this->level === 'error';
    }
}
```

### negative-space-comment

A comment defending the code against a strawman ("not random", "no magic", "not a coincidence", "not dead code")

```php
----------[ Bad ]----------

public function get(string $code): SkuEntry
{
    // no magic here; a missing code yields an empty entry
    return new SkuEntry();
}

----------[ Good ]----------

/**
 * The affirmative form of the same note: it states what the registry DOES, so a reader never has
 * to be talked out of a suspicion the code never raised.
 */
public function entry(string $code): SkuEntry
{
    // an unknown code answers with an empty entry, so a caller never tests for absence
    return new SkuEntry();
}
```

### restated-comment

An inline comment that only spells the statement below it back in prose ("// save the order" over `$this->orders->save($order)`)

```php
----------[ Bad ]----------

public function load(int $cartId): void
{
    // store the cart snapshot
    $this->snapshot = CartSnapshot::of($cartId);
}

----------[ Good ]----------

/**
 * The comment now carries what the code cannot: WHY the snapshot is taken once.
 */
public function open(int $cartId): void
{
    // the cart is frozen at checkout entry, so a coupon is weighed against the cart as it was then
    $this->snapshot = CartSnapshot::of($cartId);
}
```

### stacked-docblock

Two or more docblocks stacked on one declaration — PHP reads only the last, so the ones above it are documentation nobody sees

```php
----------[ Bad ]----------

/**
 * A plain on-disk report file — NOT an Eloquent model, even though it has a
 * save(). Mutating-then-saving one is just building a file, not the model-at-the-
 * call-site sin.
 */
final class ReportFile
{
    public string $name = '';

    public string $contents = '';

    private const string EXTENSION = '.csv';

    /**
     * Writes the report where the export job expects it.
     */
    /**
     * The second block PHP never hands to a reader.
     */
    public function save(): void
    {
        // write to disk
    }

    /**
     * Writes the report where the export job expects it — the second block folded in, so the one
     * block PHP hands a reader says everything both used to.
     */
    public function archive(): void
    {
        $this->save();
    }
}

----------[ Good ]----------

/**
 * Writes the report where the export job expects it — the second block folded in, so the one
 * block PHP hands a reader says everything both used to.
 */
public function archive(): void
{
    $this->save();
}
```
