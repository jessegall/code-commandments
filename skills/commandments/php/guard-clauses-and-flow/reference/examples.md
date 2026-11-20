# Guard clauses & flow — check at the top, then go straight — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### if-else-ladder

if/elseif ladder of 4+ branches (should be match/dispatch)

```php
----------[ Bad ]----------

public function band(int $grams): string
{
    if ($grams < 250) {
        return 'letter';
    } elseif ($grams < 2_000) {
        return 'parcel-s';
    } elseif ($grams < 10_000) {
        return 'parcel-m';
    } else {
        return 'parcel-l';
    }
}

----------[ Good ]----------

public function bandByMatch(int $grams): string
{
    return match (true) {
        $grams < 250 => 'letter',
        $grams < 2_000 => 'parcel-s',
        $grams < 10_000 => 'parcel-m',
        default => 'parcel-l',
    };
}
```

### inline-throw

`?? throw` fed into a call or dereferenced on the same line (inline throw mid-expression)

```php
----------[ Bad ]----------

public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

----------[ Good ]----------

public function carrierNameGuarded(Shipment $shipment): string
{
    if ($shipment->carrier === null) {
        throw CarrierMissing::for($shipment->id);
    }

    return $shipment->carrier->displayName();
}
```
