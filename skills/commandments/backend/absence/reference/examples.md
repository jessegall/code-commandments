# Absence — model "might not be there" honestly — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### blank-string-default

`string $x = ''` standing in for absence — then asked `$x === ''`

```php
----------[ Bad ]----------

public static function of(string $heading, string $strapline = ''): string
{
    if ($strapline === '') {
        return $heading;
    }

    return $heading . ' — ' . $strapline;
}

----------[ Good ]----------

public static function lined(string $heading, ?string $strapline = null): string
{
    if ($strapline === null) {
        return $heading;
    }

    return $heading . ' — ' . $strapline;
}
```

### blank-string-on-the-wire

a total `string` field whose TypeScript reader — holding this very type — asks it `=== ''`: the blank means "missing", and only the far side says so

```php
----------[ Bad ]----------

public function __construct(
    public string $channel,
    public string $socket,
    public int $pollMs,
) {}

----------[ Good ]----------

// Where the stock room's live counts come from. A shop with no socket has NO socket, and the type
// says so — so the browser asks for absence instead of decoding a blank.

final readonly class StockFeed
{
    public function __construct(
        public string $channel,
        public ?string $socket = null,
        public int $pollMs = 2000,
    ) {}

    public function isSocketed(): bool
    {
        return $this->socket !== null;
    }
}
```

### cancelled-coalesce

`??` cancelled by the comparison it sits in — `($x ?? '') !== ''`

```php
----------[ Bad ]----------

public function hasSession(?string $sessionId): bool
{
    return ($sessionId ?? '') !== '';
}

----------[ Good ]----------

public function hasSessionStated(?string $sessionId): bool
{
    return $sessionId !== null && $sessionId !== '';
}
```

### conditional-array-spread

An array is built by spreading a conditional element — `...($x ? ['k' => $x] : [])` / `array_merge($base, $cond ? [...] : [])` — the ternary-into-empty-array noise that hides 'include when present'

```php
----------[ Bad ]----------

public function lines(): array
{
    return [
        'number' => $this->number,
        ...($this->coupon === null ? [] : ['discount' => ['coupon' => $this->coupon, 'applied' => true]]),
    ];
}

----------[ Good ]----------

public function toArray(): array
{
    return Payload::of(key: $this->key, label: $this->label, icon: $this->icon);
}
```

### de-nulled-finder

Missing = broken state returned as `?T`/null instead of throwing (a `?T` finder whose callers de-null it)

```php
----------[ Bad ]----------

public function byBarcode(string $barcode): ?Product
{
    return Product::query()->where('barcode', $barcode)->first();
}

----------[ Good ]----------

// in Shop\Catalog\ProductFinder
// Resolve-or-throw: a scanned barcode must exist, so the absence is decided
// once at the source and the return type tells the truth.

public function requireByBarcode(string $barcode): Product
{
    return Product::query()->where('barcode', $barcode)->first()
        ?? throw ProductNotFound::forBarcode($barcode);
}

// The absence the finder used to hand back as null, decided once and named. Every caller that
// de-nulled the `?Product` is now spared the question.

final class ProductNotFound extends RuntimeException
{
    public static function forBarcode(string $barcode): self
    {
        return new self("No product is registered under barcode {$barcode}.");
    }
}
```

### erased-null-object

A blank-rendering Null Object written into a `string` slot — coerced back to `''`

```php
----------[ Bad ]----------

public static function of(string $consignment, string $complaint = new BlankText): self
{
    return new self($consignment, $complaint);
}

----------[ Good ]----------

public static function noted(string $consignment, ?string $complaint = null): self
{
    return new self($consignment, $complaint);
}
```

### nullable-callback

Nullable callback normalised in the body instead of a Null Object default

```php
----------[ Bad ]----------

public function run(Closure $work, Closure | null $onRetry = null): mixed
{
    while (true) {
        $this->attempts++;

        try {
            return $work();
        } catch (\Throwable $e) {
            if ($onRetry) {
                $onRetry($this->attempts);
            }

            if ($this->attempts >= 3) {
                throw $e;
            }
        }
    }
}

----------[ Good ]----------

public function runWith(Closure $work, Invokable $onRetry = new NoOp): mixed
{
    while (true) {
        $this->attempts++;

        try {
            return $work();
        } catch (\Throwable $e) {
            $onRetry($this->attempts);

            if ($this->attempts >= 3) {
                throw $e;
            }
        }
    }
}
```

### option-as-nullable

`Option<T>` used as a nullable costume — `?Option`, `Option | null`, `unwrapOr(null)`

```php
----------[ Bad ]----------

public function locate(string $email): ?Option
{
    return Option::none();
}

----------[ Good ]----------

public function locateHonestly(string $email): Option
{
    return Option::fromTruthy($email);
}
```
