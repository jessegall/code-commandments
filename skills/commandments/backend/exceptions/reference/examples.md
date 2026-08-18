# Exceptions — fail hard, fix once — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### generic-exception

`throw new <bare SPL>` (RuntimeException/LogicException/…) instead of a named type

```php
----------[ Bad ]----------

public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

----------[ Good ]----------

public function carrierNameNamed(Shipment $shipment): string
{
    return $shipment->carrier?->displayName()
        ?? throw CarrierMissing::for($shipment->id);
}
```

### message-at-throw

Message string built at the throw site (no domain values / named factory)

```php
----------[ Bad ]----------

public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

----------[ Good ]----------

public function carrierNameOrFail(Shipment $shipment): string
{
    $carrier = $shipment->carrier ?? throw CarrierMissing::for($shipment->id);

    return $carrier->displayName();
}
```

### swallow-catch

`catch` whose only effect is `return null/false/[]`; empty catch (silent swallow)

```php
----------[ Bad ]----------

public function forecast(string $city): array
{
    try {
        $body = $this->http->get("https://weather.test/{$city}");

        return (array) json_decode($body, true);
    } catch (\Throwable $e) {
        return [];
    }
}

----------[ Good ]----------

public function forecastOrThrow(string $city): array
{
    try {
        $body = $this->http->get("https://weather.test/{$city}");

        return (array) json_decode($body, true);
    } catch (\Throwable $e) {
        report($e);

        throw $e;
    }
}
```

### wrapping-without-cause

Wrapping a caught exception without passing it as `previous`/cause

```php
----------[ Bad ]----------

public function upload(string $path): void
{
    try {
        $this->pushToBucket($path);
    } catch (\Throwable $storageError) {
        throw new IntegrationException($path);
    }
}

----------[ Good ]----------

public function uploadChecked(string $path): void
{
    try {
        $this->pushToBucket($path);
    } catch (\Throwable $storageError) {
        throw new IntegrationException($path, previous: $storageError);
    }
}
```
