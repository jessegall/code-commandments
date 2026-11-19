# Role vocabulary — the name is the contract — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### hand-rolled-registry

A class writes its own keyed store (fill under a key, throw on a missing key) instead of extending the one Registry base

```php
----------[ Bad ]----------

// A generic keyed store behind a typed façade — the righteous twin of a derived argument. `add()` takes
// the subject once and derives the key itself, and the inner `register($carrier->key, $carrier)` only
// looks like the sin: `register` accepts `mixed`, so it provably cannot derive anything from what it
// holds, and the key beside the item is the only way the value gets there (#504).

final class CarrierRegistry
{
    /**
     * @var array<string, Carrier>
     */
    private array $carriers = [];

    public function add(Carrier $carrier): static
    {
        return $this->register($carrier->key, $carrier);
    }

    public function get(string $key): Carrier
    {
        return $this->carriers[$key] ?? throw new UnknownCarrier($key);
    }

    /**
     * The generic half: a store that knows nothing about what it stores.
     */
    private function register(string $key, mixed $item): static
    {
        $this->carriers[$key] = $item;

        return $this;
    }
}

----------[ Good ]----------

// The carriers the shop ships with, kept under their carrier codes by the shared Registry.

final class CarrierRoster extends Registry
{
}
```

### nullable-registry-lookup

A keyed-store `get()` that returns `null` on a miss (should resolve-or-throw)

```php
----------[ Bad ]----------

public function get(string $key): ?object
{
    return $this->channels[$key] ?? null;
}

----------[ Good ]----------

// in Shop\Support\NotificationChannels
// The FIX: the store resolves-or-throws. A missing key is a named failure at the source
// (`UnknownChannel::forKey($key)`), never a `null` every caller has to re-decide about.

public function resolve(string $key): object
{
    return $this->channels[$key] ?? throw UnknownChannel::forKey($key);
}

// The failure the nullable lookup used to hand back as null. Naming it is what lets the store
// resolve-or-throw, so no caller re-decides what a missing channel means.

final class UnknownChannel extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No notification channel is registered under {$key}.");
    }
}
```
