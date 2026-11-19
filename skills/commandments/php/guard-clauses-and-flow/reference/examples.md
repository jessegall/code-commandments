# Guard clauses & flow — check at the top, then go straight — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### coalesced-loop-subject

`foreach ($x[$k] ?? [] as …)` — the absence check buried in the loop header instead of stated as a guard

```php
----------[ Bad ]----------

public function fanOut(string $carrier, array $manifest): void
{
    foreach ($manifest[$carrier] ?? [] as $parcel) {
        $this->queued[$carrier][] = $parcel;
    }
}

----------[ Good ]----------

public function fanOutGuarded(string $carrier, array $manifest): void
{
    if (! isset($manifest[$carrier])) {
        return;
    }

    foreach ($manifest[$carrier] as $parcel) {
        $this->queued[$carrier][] = $parcel;
    }
}
```

### deep-nesting

`if` nested 3-deep (a pyramid — hoist guards / extract)

```php
----------[ Bad ]----------

public function resolve(Product $product, array $overrides, string $region): int
{
    if (array_key_exists($region, $overrides)) {
        if ($product->price_cents > 0) {
            if ($overrides[$region] < $product->price_cents) {
                return $overrides[$region];
            }
        }
    }

    return $product->price_cents;
}

----------[ Good ]----------

// The same resolution flattened: preconditions become guard clauses, so the
// happy path runs unindented at the top level.

public function resolveFlat(Product $product, array $overrides, string $region): int
{
    if (! array_key_exists($region, $overrides)) {
        return $product->price_cents;
    }

    if ($product->price_cents <= 0) {
        return $product->price_cents;
    }

    return min($overrides[$region], $product->price_cents);
}
```

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

### non-counting-for

A `for` loop that looks like it's counting, but its step actually assigns the next item instead of incrementing a counter.

```php
----------[ Bad ]----------

public function nearest(object $widget): string
{
    for ($one = $widget; $one !== null; $one = $one->stacked ? $one->above : null) {
        if ($one->caption !== '') {
            return $one->caption;
        }
    }

    return 'untitled';
}

----------[ Good ]----------

public function nearestWhile(object $widget): string
{
    $one = $widget;

    while ($one !== null) {
        if ($one->caption !== '') {
            return $one->caption;
        }

        $one = $one->stacked ? $one->above : null;
    }

    return 'untitled';
}
```

### unnamed-condition

`if ($order->isPaid() && $customer->trusted && $slot->open)` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it

```php
----------[ Bad ]----------

public function dispatch(Crate $crate): void
{
    if ($crate->isPacked() && $crate->hasLabel() && $this->bay->isFree()) {
        $this->bay->take($crate);
        $this->sent[] = $crate->reference;
    }
}

----------[ Good ]----------

// in Shop\Dispatch\BayDispatcher
public function dispatchTold(Crate $crate): void
{
    if ($crate->readyFor($this->bay)) {
        $this->bay->take($crate);
        $this->sent[] = $crate->reference;
    }
}

// in Shop\Dispatch\Crate
public function readyFor(LoadingBay $bay): bool
{
    return $this->packed && $this->labelled && $bay->isFree();
}
```
