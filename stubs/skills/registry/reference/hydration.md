# Hydrate the registry in a service provider — once, from config

A registry starts EMPTY. The question is *where* its entries get registered. The
answer is a **service provider**, driven by config — not the registry's constructor,
not scattered `register()` calls across the app.

## The pattern

Bind the registry as a singleton and hydrate it once at boot, iterating the config
that declares the members:

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

## Why the service provider

- **One wiring site.** Every member is registered in one place that runs at boot, so
  the registry's contents are legible and change in one spot — not re-derived per
  caller or hidden in a constructor.
- **Config-driven.** The member set lives as DATA in config; the provider just walks
  it. Adding a member is a config edit, not a code change to the registry. This is the
  positive form of what `PreferConfigDrivenRegistry` flags — an enum/match that
  hardcodes a set config already declares should become a config-hydrated registry.
- **Singleton.** Bind it `singleton` so every consumer shares the one hydrated
  instance; a fresh, empty registry per resolve is a silent bug.
- **Lazy values.** Register a `fn () => $app->make($class)` factory, not an eager
  instance, so members are constructed on first `get()`, not at boot.

## Anti-patterns

- **Hydrating in the constructor** (`new Registry()` that fills `$this->items` from
  its own `discover()`): that is a *catalog* (built/discovered), not a registry
  (registered into). Name it `*Catalog`/`*Map` and own the store — see
  `reference/naming.md` and the `RegistryBaseBypass` trap in `reference/base-class.md`.
- **Scattered `register()` calls** across controllers/services: the member set is no
  longer legible in one place and drifts. Move them into the provider.

Pairs with **PreferConfigDrivenRegistry** (a hardcoded set that config already
declares → hydrate a registry in a provider instead).
