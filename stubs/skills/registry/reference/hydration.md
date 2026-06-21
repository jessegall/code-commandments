# Hydrate the registry in a service provider — once, from config

A registry starts EMPTY. The question is *where* its entries get registered. The
answer is a **service provider**, driven by config — not the registry's constructor,
not scattered `register()` calls across the app.

## The pattern

Bind the registry as a singleton and hydrate it once, up front — preferably at boot
(a service provider's `register()`/`boot()`), iterating the config that declares the
members. If a registry genuinely needs data only available later, hydrating it once
*after the application has booted* is fine too — the rule is that registration is
EAGER and in one place, not the exact moment:

```php
// config/billing.php  — the members live as DATA
return [
    'gateways' => [
        'stripe' => \App\Billing\StripeGateway::class,
        'paypal' => \App\Billing\PaypalGateway::class,
    ],
];

// A service provider — the ONE place the registry is hydrated
public function register(): void
{
    $this->app->singleton(GatewayRegistry::class, function ($app) {
        $registry = new GatewayRegistry;

        foreach (config('billing.gateways') as $key => $class) {
            $registry->register($key, fn () => $app->make($class));
        }

        return $registry;
    });
}
```

Now `GatewayRegistry::get('stripe')` works everywhere, the member set is config
(add a gateway = one config line + the class), and there is exactly one wiring site.

## Hydrating after boot (when the entries aren't available yet)

Sometimes the entries can only be produced once the whole app has booted — other
providers must have registered first, or discovery needs the booted container. Do it
in `boot()` via `$this->app->booted(...)`, which runs after every provider has
booted. It is still EAGER and one-shot — just deferred to the booted moment, NOT a
lazy build on first `get()`:

```php
public function boot(): void
{
    // Runs once, after the application has finished booting.
    $this->app->booted(function () {
        $registry = $this->app->make(NodeRegistry::class);

        // Discovery/reflection lives in a collaborator; the registry just gets fed.
        $registry->registerMany(
            $this->app->make(NodeDiscovery::class)->scan(),
        );
    });
}
```

Boot is preferred; `booted()` is the escape hatch when you genuinely need the booted
app. Both are eager registration — the lookup (`get`/`all`) still never builds.

## Why the service provider

- **One wiring site.** Every member is registered in one place that runs up front (at
  boot, or once the app has booted), so the registry's contents are legible and change
  in one spot — not re-derived per caller or hidden in a constructor.
- **Config-driven.** The member set lives as DATA in config; the provider just walks
  it. Adding a member is a config edit, not a code change to the registry. This is the
  positive form of what `PreferConfigDrivenRegistry` flags — an enum/match that
  hardcodes a set config already declares should become a config-hydrated registry.
- **Singleton.** Bind it `singleton` so every consumer shares the one hydrated
  instance; a fresh, empty registry per resolve is a silent bug.
- **Lazy values.** Register a `fn () => $app->make($class)` factory, not an eager
  instance, so members are constructed on first `get()`, not at boot.

## Read-only once hydrated

Hydration happens once, up front — preferably at boot, or just after the application
has booted if the data is only available then. **When** it happens is flexible; that
it is EAGER (registered up front, not built on first read) is the rule. Every lookup
thereafter is a **dumb read** — it never writes or builds the store:

- **No lazy hydration.** `public function all() { return $this->items ??= $this->build(); }`
  builds the store on first read. Hydrate eagerly up front instead; `all()` just
  returns `$this->items`.
- **No populate-on-miss.** `return $this->items[$k] ??= $this->make($k);` creates and
  caches on a miss — that is a *cache/factory*, not a registry. Register everything up
  front; `get()` resolves-or-throws, never creates.
- **No discovery/reflection in a lookup.** `Discover::in(...)` / `new ReflectionClass`
  inside `get()`/`all()` belongs in a separate `*Discovery`/`*Reflector` collaborator
  that the boot path uses to produce the entries it registers.

A class that builds, memoises-on-miss, or reflects on read is a cache/factory wearing
a registry's name — name it honestly (`*Cache`/`*Factory`), or make it a real,
eagerly-hydrated registry.

## Anti-patterns

- **Hydrating in the constructor** (`new Registry()` that fills `$this->items` from
  its own `discover()`): that is a *catalog* (built/discovered), not a registry
  (registered into). Name it `*Catalog`/`*Map` and own the store — see
  `reference/naming.md` and the `RegistryBaseBypass` trap in `reference/base-class.md`.
- **Scattered `register()` calls** across controllers/services: the member set is no
  longer legible in one place and drifts. Move them into the provider.

Pairs with **PreferConfigDrivenRegistry** (a hardcoded set that config already
declares → hydrate a registry in a provider instead) and enforced by
**EagerRegistry** (a registry lookup that writes/builds the store → lazy hydration /
populate-on-miss).
