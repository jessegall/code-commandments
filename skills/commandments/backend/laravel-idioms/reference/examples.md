# Laravel idioms — typed access, injected deps, behaviour on the model — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### config-read

`config('…')` read inside a class

```php
----------[ Bad ]----------

public function search(array $filters): array
{
    $perPage = config('shop.catalog.per_page');

    // formerly filtered in PHP; refactored into the query builder in v3
    $term = $filters['q'];
    $sort = $filters['sort'];

    return $this->run($term, $sort, $perPage);
}

----------[ Good ]----------

// in Shop\Catalog\CatalogSearchService
// Injects the typed settings object instead of reading config in the body.

public function searchTop(string $term, string $sort): array
{
    return $this->run($term, $sort, $this->settings->perPage);
}

// The config values the catalog actually uses, read once at the edge and typed. Every body that
// used to reach for `config()` takes this instead, and gets an `int` rather than a `mixed`.

final class CatalogSettings
{
    public function __construct(
        public readonly int $perPage = 24,
    ) {}
}
```

### container-reach

`app()`/`resolve()` reach inside a container-resolved class

```php
----------[ Bad ]----------

public function charge(string $token, int $amountCents): bool
{
    $gateway = app(PaymentGatewayRegistry::class)->get('default');

    if ($amountCents <= 0) {
        throw new \RuntimeException("Cannot charge a non-positive amount: {$amountCents}");
    }

    Log::info('charging', ['amount' => $amountCents]);

    return $gateway->send($token, $amountCents);
}

----------[ Good ]----------

// in Shop\Services\PaymentProcessor
public function __construct(private readonly PaymentGatewayRegistry $gateways) {}

// in Shop\Services\PaymentProcessor
// The registry is a declared constructor parameter, so the class states what it needs and the
// container hands it over — nothing reaches back into the container from the body.

public function captureAuthorised(int $amountCents): void
{
    $this->gateways->get('default')->capture($amountCents);
}
```

### dead-config-key

A config key nothing reads — dead surface left behind by a deleted feature, which new code may wrongly adopt

```php
----------[ Bad ]----------

static fn (): null => null;

----------[ Good ]----------

public function register(): void
{
    $this->app->singleton('shop.kiosk.timeout', static fn (): int => (int) config('kiosk.idle_timeout'));
    $this->app->singleton('shop.relay.heartbeat', static fn (): int => (int) config('relay.heartbeat_seconds'));
    $this->app->singleton('shop.stocktake.cycle', static fn (): int => (int) config('stocktake.cycle_days'));
}
```

### dead-event-wiring

An `Event::listen` on an event class no live code path can fire — a listener chain that dead-ends but reads as live wiring

```php
----------[ Bad ]----------

public function boot(): void
{
    Event::listen(StockReconciled::class, 'Shop\\Listeners\\NotifyWarehouse');
}

----------[ Good ]----------

// in Shop\Providers\StockEventProvider
public function register(): void
{
    Event::listen(PriceChanged::class, 'Shop\\Listeners\\RepricePublications');
}

// The live dispatcher that keeps PriceChanged's listener honest.

final class PriceBroadcaster
{
    public function announce(string $sku, int $cents): void
    {
        event(new PriceChanged($sku, $cents));
    }
}
```

### duplicated-config-default

A config key whose default is stated TWICE — once in the config file, again as the reader's inline fallback — two sources of truth that drift silently

```php
----------[ Bad ]----------

public function idleTimeout(): int
{
    return $this->intOr('kiosk.idle_timeout', 120);
}

----------[ Good ]----------

// in Shop\Support\ShopConfig
// `config/kiosk.php` owns the default, so the reader states nothing about absence — it asks for
// the value and there is one place to edit it.

public function idleTimeoutSeconds(): int
{
    return $this->int('kiosk.idle_timeout');
}

// in Shop\Support\ShopConfig
private function int(string $key): int
{
    return 0;
}
```

### facade-call

Laravel facade call (`Cache::`, `Log::`, `Mail::` …)

```php
----------[ Bad ]----------

public function notify(string $email, string $type): void
{
    $template = config('shop.templates.' . $type);

    Mail::raw($template, function ($message) use ($email) {
        $message->to($email);
    });
}

----------[ Good ]----------

// in Shop\Services\NotificationService
public function __construct(private readonly Mailer $mailer) {}

// in Shop\Services\NotificationService
public function notifyClean(string $email, string $template): void
{
    $this->mailer->raw($template, function ($message) use ($email) {
        $message->to($email);
    });
}
```

### mass-update-at-call-site

Bare `$model->update([...])` mass-array update at a call site

```php
----------[ Bad ]----------

public function verify(Customer $customer): void
{
    $customer->update([
        'verified' => true,
        'verified_at' => $this->now,
    ]);
}

----------[ Good ]----------

// in Shop\Customers\CustomerVerifier
public function verifyNamed(Customer $customer): void
{
    $customer->markVerified($this->now);
}

// in Shop\Models\Customer
public function markVerified(string $at): void
{
    $this->verified = true;
    $this->verified_at = $at;
    $this->save();
}
```

### model-mutation-at-call-site

Set-property-then-`save()` at a call site (should be an intention method)

```php
----------[ Bad ]----------

public function suspend(Customer $customer, string $reason): void
{
    $customer->suspended = true;
    $customer->suspended_reason = $reason;
    $customer->save();
}

----------[ Good ]----------

// in Shop\Customers\CustomerUpdater
public function suspendNamed(Customer $customer, string $reason): void
{
    $customer->suspend($reason);
}

// in Shop\Models\Customer
// Where every call site's poking-and-saving went: the transition named once, on the model that
// owns the columns it writes.

public function suspend(string $reason): void
{
    $this->suspended = true;
    $this->suspended_reason = $reason;
    $this->save();
}
```

### orphaned-binding

A container binding whose abstract nothing ever resolves — dead wiring that reads as load-bearing and survives every refactor

```php
----------[ Bad ]----------

public function boot(): void
{
    if (! $this->ratesEnabled) {
        return;
    }

    $this->app->instance(CourierRates::class, new TableCourierRates());
}

----------[ Good ]----------

// in Shop\Providers\ShippingServiceProvider
// The rates registration went out with the courier that needed it. What is left is wiring a
// consumer actually asks for: RegionCoverage type-hints a ZoneTable, so this binding answers a
// resolve.

public function register(): void
{
    $this->app->singleton(ZoneTable::class);
}

// A consignment's zone already knows which regions it serves. Asking through a
// zone table keyed by the consignment's id — then testing membership out here —
// exiles the answer from where it lives. Move it: `$consignment->covers($region)`.

final class RegionCoverage
{
    public function __construct(private readonly ZoneTable $zones) {}

    public function describe(): string
    {
        return 'region coverage via ' . ZoneTable::class;
    }

    public function covers(Consignment $consignment, string $region): bool
    {
        return $this->zones->lookup($consignment->zoneId)->includes($region);
    }
}
```

### raw-request-input

Raw `->input()/->get()/->query()/->post()` on a Request

```php
----------[ Bad ]----------

public function search(Request $request): array
{
    $term = $request->input('q');
    $category = $request->input('category');

    return Product::query()
        ->where('name', 'like', "%{$term}%")
        ->where('category', $category)
        ->get()
        ->all();
}

----------[ Good ]----------

// in Shop\Http\Controllers\ProductController
// The same search, read through named getters on a `FormRequest` subclass — the key strings and
// their types live once, in the request, instead of at every call site.

public function searchNamed(SearchProductRequest $request): array
{
    return Product::query()
        ->where('name', 'like', "%{$request->term()}%")
        ->where('category', $request->category())
        ->get()
        ->all();
}

// in Shop\Http\Requests\SearchProductRequest
public function term(): string
{
    return $this->string('q')->toString();
}
```

### request-accessor-recast

Re-coercing a typed request accessor at a call site — `$request->string('id')->toString()` or `(string) $request->string('id')` instead of a named getter on a request class

```php
----------[ Bad ]----------

public function search(Request $request): array
{
    $term = $request->string('q')->toString();

    return Product::query()
        ->where('name', 'like', "%{$term}%")
        ->get()
        ->all();
}

----------[ Good ]----------

// in Shop\Http\Controllers\TermController
// The coercion moved onto the typed request as `term()`, so the call site asks a named getter for
// an already-`string` value instead of recasting an accessor.

public function searchNamed(SearchProductRequest $request): array
{
    return Product::query()
        ->where('name', 'like', "%{$request->term()}%")
        ->orderBy('name')
        ->get()
        ->all();
}

// in Shop\Http\Requests\SearchProductRequest
public function category(): string
{
    return $this->string('category')->toString();
}
```
