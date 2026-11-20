# Fix at the source — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### constructor-side-effect

A constructor that performs a side effect on a collaborator and throws away the result, so simply creating the object changes something outside it.

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

### mutable-static-state

A write to a static property — really a global variable with a namespace attached — where whichever write happens last wins, so the order code runs in changes the result.

```php
----------[ Bad ]----------

public static function load(array $rates): void
{
    self::$table = $rates;
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

### unread-collaborator

a collaborator promoted into a private field by the constructor and never read anywhere in the class — the class asks for something it does not use, and only a search of the whole type says so

```php
----------[ Bad ]----------

public function __construct(
    private readonly Mailer $mailer,
    private readonly CarrierRegistry $carriers,
) {}

----------[ Good ]----------

public function __construct(private readonly Mailer $mailer) {}
```
