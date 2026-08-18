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

// Injects the typed settings object instead of reading config in the body.

public function searchTop(string $term, string $sort): array
{
    return $this->run($term, $sort, $this->settings->perPage);
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

public function register(): void
{
    Event::listen(PriceChanged::class, 'Shop\\Listeners\\RepricePublications');
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

// `config/kiosk.php` owns the default, so the reader states nothing about absence — it asks for
// the value and there is one place to edit it.

public function idleTimeoutSeconds(): int
{
    return $this->int('kiosk.idle_timeout');
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

public function verifyNamed(Customer $customer): void
{
    $customer->markVerified($this->now);
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

public function suspendNamed(Customer $customer, string $reason): void
{
    $customer->suspend($reason);
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

// The rates registration went out with the courier that needed it. What is left is wiring a
// consumer actually asks for: RegionCoverage type-hints a ZoneTable, so this binding answers a
// resolve.

public function register(): void
{
    $this->app->singleton(ZoneTable::class);
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
```
