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

### loop-inverted-guard

Loop body (multi-statement) wrapped in an `if` instead of `continue` guard

```php
----------[ Bad ]----------

public function process(array $rows): void
{
    foreach ($rows as $row) {
        if ($row->total > 0) {
            $this->normalise($row);
            $this->persist($row);
        }
    }
}

----------[ Good ]----------

public function process(array $rows): void
{
    foreach ($rows as $row) {
        if ($row->total <= 0) {
            continue;
        }

        $this->normalise($row);
        $this->persist($row);
    }
}
```

### nested-ternary

Nested/chained ternary `$a ? $b : ($c ? $d : $e)` (hidden control flow)

```php
----------[ Bad ]----------

private function band(int $score): string
{
    return $score >= 90 ? 'A' : ($score >= 75 ? 'B' : 'C');
}

----------[ Good ]----------

// The same decision as a `match (true)` — each band reads on its own line, no
// precedence trap.

private function bandMatched(int $score): string
{
    return match (true) {
        $score >= 90 => 'A',
        $score >= 75 => 'B',
        default => 'C',
    };
}
```

### non-counting-for

a `for` whose step assigns the next thing instead of advancing a counter — a walk wearing a counted loop's clothes

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

### redundant-else

`else` after an `if` branch that already returns/throws (redundant)

```php
----------[ Bad ]----------

public function inStock(array $products): array
{
    $available = [];

    foreach ($products as $product) {
        if ($product->stock <= 0) {
            continue;
        } else {
            $available[] = $product;
        }
    }

    return $available;
}

----------[ Good ]----------

// The guard handles the absent case and `continue`s; the happy path runs
// unindented with no redundant `else`.

public function available(array $products): array
{
    $available = [];

    foreach ($products as $product) {
        if ($product->stock <= 0) {
            continue;
        }

        $available[] = $product;
    }

    return $available;
}
```

### short-circuit-statement

a bare `$a && $b->do();` statement — a short-circuit whose result nothing reads, so the operator is an `if` in disguise

```php
----------[ Bad ]----------

public function send(string $address, bool $subscribed): void
{
    $subscribed && $this->mailer->send($address, 'Your weekly digest', $this->digest());
}

----------[ Good ]----------

public function sendGuarded(string $address, bool $subscribed): void
{
    if (! $subscribed) {
        return;
    }

    $this->mailer->send($address, 'Your weekly digest', $this->digest());
}
```

### ternary-statement

a bare `$cond ? doThis() : doThat();` statement — a ternary whose value nothing reads, so it is choosing an ACTION, not a value

```php
----------[ Bad ]----------

public function collapsed(string $id, array $below): array
{
    $gone = [];

    foreach ($below[$id] as $child) {
        $this->isOpen($child->id)
            ? array_push($gone, ...$this->collapsed($child->id, $below))
            : $gone[] = $child->id;
    }

    return $gone;
}

----------[ Good ]----------

public function collapsedBranched(string $id, array $below): array
{
    $gone = [];

    foreach ($below[$id] as $child) {
        if ($this->isOpen($child->id)) {
            array_push($gone, ...$this->collapsedBranched($child->id, $below));
        } else {
            $gone[] = $child->id;
        }
    }

    return $gone;
}
```
