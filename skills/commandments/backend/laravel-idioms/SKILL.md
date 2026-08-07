---
name: commandments-backend-laravel-idioms
description: "Use the framework's typed/injected mechanisms and keep behaviour on the model — read request input through TYPED accessors behind named getters (never raw `->input()`), read a Fluent/ValueBag through typed accessors (never untyped `->get()`), inject every dependency through the constructor (never `app()`/facade/`new`), query through named Eloquent scopes (not repeated where-clauses), and mutate through intention-revealing model methods (never a bare `update([...])` or `$model->x = y; save()` at a call site). Read this BEFORE you call `->input()`/`->get()`, reach for a dependency, write a query, or update a model."
---

# Laravel idioms — typed access, injected deps, behaviour on the model

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model
> to hang behaviour on. Reach for those. Raw `->input()`, untyped `->get()`, `app()`-in-a-method, a
> repeated `where()` chain, and a column-poke-then-`save()` are all the same mistake: throwing away a
> type, a wire, or a name the framework was holding for you.

## The principle

The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model to
hang behaviour on. Reach for those. Raw `->input()`, an untyped `->get()`, `app()`-in-a-method, a `where()`
chain repeated at call sites, and a column-poke-then-`save()` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.

Read request input through the request's **typed accessors**, exposed as **named getter methods on the
request class** — the one place the type is settled, so every call site reads a typed value by intent
instead of re-coercing `mixed`. An MCP tool's input is a request like any other: give each tool its own
named request class (the analogue of a `FormRequest`), with its keys, rules and types in one place, and
read *that* — never the raw request inside `handle()`.

Hold every dependency as a required constructor parameter, never resolved by hand from the container.
Express a query concept that recurs across call sites as a **named Eloquent scope**, so the column knowledge
lives in one place instead of being re-typed wherever you query. And mutate a model through
**intention-revealing methods** (`$order->markPaid()`) that say what changed and why — not a bare
`update([...])` or a set-property-then-`save()` smeared across the call site.

## Rules

- Inject a typed config object; never read `config('…')` inside a class.
  _Inject a typed config value object._
- Declare dependencies in the constructor; never reach into the container with `app()`/`resolve()` from a resolved class.
  _Declare the dependency as a constructor parameter._
- Config is an interface: every key exists because something reads it. When the last reader goes, the key goes with it.
  _Delete the key (and its env var), or restore the reader the feature lost._
- A listener exists to answer a dispatch. When the last dispatcher goes, the listener goes with it.
  _Delete the registration (and the listener, if nothing else reaches it) — or restore the dispatch the listener was waiting for._
- The config FILE owns the default. A reader asks for the value; it does not restate what the value should be when absent.
  _Drop the reader's fallback and let the config file answer — or delete the key from the file and let the reader's default be the one truth._
- Inject the dependency; never call a Laravel facade (`Cache::`, `Log::`, `Mail::`) inside a class.
  _Constructor-inject the dependency behind its interface._
- Mutate a model through an intention method; never `$model->update([...])` an anonymous array of columns at a call site.
  _An intention method on the model (`$order->markPaid()`)._
- Mutate a model through an intention method; don't set-property-then-`save()` at a call site.
  _An intention method on the model (`$order->suspend($reason)`)._
- Wiring is code: a binding exists to answer a resolve. When the last consumer goes, the binding goes with it.
  _Delete the registration (and the implementation it names, if that is dead too)._
- Read request input through a typed accessor (`$request->string('x')`); never raw `->input()`/`->get()`/`->query()`.
  _A named getter on a `FormRequest` subclass (`$request->productId()`)._
- Expose a named getter on a typed request class; don't re-coerce a typed accessor (`$request->string('id')->toString()`) at a call site.
  _A named getter on a typed request class returning the coerced value._

## Bad → good

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

## When it fires

- `config('…')` read inside a class — `ConfigReadDetector`
- `app()`/`resolve()` reach inside a container-resolved class — `ContainerReachDetector`
- A config key nothing reads — dead surface left behind by a deleted feature, which new code may wrongly adopt — `DeadConfigKeyDetector`
- An `Event::listen` on an event class no live code path can fire — a listener chain that dead-ends but reads as live wiring — `DeadEventWiringDetector`
- A config key whose default is stated TWICE — once in the config file, again as the reader's inline fallback — two sources of truth that drift silently — `DuplicatedConfigDefaultDetector`
- Laravel facade call (`Cache::`, `Log::`, `Mail::` …) — `FacadeCallDetector`
- Bare `$model->update([...])` mass-array update at a call site — `MassUpdateAtCallSiteDetector`
- Set-property-then-`save()` at a call site (should be an intention method) — `ModelMutationAtCallSiteDetector`
- A container binding whose abstract nothing ever resolves — dead wiring that reads as load-bearing and survives every refactor — `OrphanedBindingDetector`
- Raw `->input()/->get()/->query()/->post()` on a Request — `RawRequestInputDetector`
- Re-coercing a typed request accessor at a call site — `$request->string('id')->toString()` or `(string) $request->string('id')` instead of a named getter on a request class — `RequestAccessorRecastDetector`

## Checklist

- [ ] Inject a typed config object; never read `config('…')` inside a class.
- [ ] Declare dependencies in the constructor; never reach into the container with `app()`/`resolve()` from a resolved class.
- [ ] Config is an interface: every key exists because something reads it. When the last reader goes, the key goes with it.
- [ ] A listener exists to answer a dispatch. When the last dispatcher goes, the listener goes with it.
- [ ] The config FILE owns the default. A reader asks for the value; it does not restate what the value should be when absent.
- [ ] Inject the dependency; never call a Laravel facade (`Cache::`, `Log::`, `Mail::`) inside a class.
- [ ] Mutate a model through an intention method; never `$model->update([...])` an anonymous array of columns at a call site.
- [ ] Mutate a model through an intention method; don't set-property-then-`save()` at a call site.
- [ ] Wiring is code: a binding exists to answer a resolve. When the last consumer goes, the binding goes with it.
- [ ] Read request input through a typed accessor (`$request->string('x')`); never raw `->input()`/`->get()`/`->query()`.
- [ ] Expose a named getter on a typed request class; don't re-coerce a typed accessor (`$request->string('id')->toString()`) at a call site.

## Related skills

- [`backend/value-objects`](../value-objects/SKILL.md) — a typed request getter / typed bag read returns the typed value the data should already be; raw `->input()` is the loose-array smell at the HTTP edge.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — read input typed at the boundary so nothing downstream re-coerces a `mixed`.
- [`backend/absence`](../absence/SKILL.md) — a typed accessor for an optional field still answers "can this be missing?" honestly (a nullable getter vs a defaulted one), not a bare `->input($k, $default)`.
