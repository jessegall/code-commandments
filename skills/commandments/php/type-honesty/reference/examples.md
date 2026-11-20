# Type honesty — the type must not lie — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### filled-parameter

a `void` method that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed

```php
----------[ Bad ]----------

private function fillCounts(ExportState $state, string $run): void
{
    $state->rows = strlen($this->http->get("https://reports.test/runs/{$run}"));
}

----------[ Good ]----------

// in Shop\Reporting\ExportStateLoader
public function loadCounted(string $run): ExportState
{
    return new ExportState($this->countedRows($run));
}

// in Shop\Reporting\ExportStateLoader
private function countedRows(string $run): int
{
    return strlen($this->http->get("https://reports.test/runs/{$run}"));
}
```

### impossible-guard

`if ($order === null)` where `$order` is typed `Order`, with no `?` — one of the two is wrong, and both are worth knowing

```php
----------[ Bad ]----------

public function hasTranslation(Language $language): bool
{
    if ($language === null) {
        return false;
    }

    return isset($this->texts[$language->code]);
}

----------[ Good ]----------

public function hasTextIn(Language $language): bool
{
    return isset($this->texts[$language->code]);
}
```

### refused-bequest

A method that overrides or implements an inherited one only to throw: the class claims a contract it refuses to keep.

```php
----------[ Bad ]----------

public function post(int $cents): void
{
    throw LedgerIsReadOnly::forPosting($cents);
}

----------[ Good ]----------

// The report's view of a ledger: it holds one and offers only what a report may do, so it has nothing to refuse.

final class LedgerReport
{
    public function __construct(private readonly LedgerBook $ledger) {}

    public function balance(): int
    {
        return $this->ledger->balance();
    }
}
```

### scratch-state-restore

Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input

```php
----------[ Bad ]----------

public function revalue(string $basis): void
{
    $previous = $this->basis;
    $this->basis = $basis;

    $this->trail[] = sprintf('priced on %s', $this->basis);

    $this->basis = $previous;
}

----------[ Good ]----------

// The FIX: the basis is this call's input, so it stays a PARAMETER and is read from there —
// `$this->basis` is never written, and the save/restore pair disappears with it.

public function revalueOn(string $basis, int $lots): void
{
    for ($lot = 0; $lot < $lots; $lot++) {
        $this->trail[] = sprintf('lot %d priced on %s', $lot, $basis);
    }
}
```

### unused-parameter

A parameter of a method no subclass can override that its body never reads: every caller must pass a value nothing uses.

```php
----------[ Bad ]----------

private function variance(int $counted, int $expected, string $countedBy): int
{
    return $counted - $expected;
}

----------[ Good ]----------

private function varianceOf(int $counted, int $expected): int
{
    return $counted - $expected;
}
```

### unused-private-member

A private method nothing in its class calls, or a private property nothing in its class reads: dead code the class still carries.

```php
----------[ Bad ]----------

private function average(): float
{
    return $this->sales === [] ? 0.0 : $this->total() / count($this->sales);
}

----------[ Good ]----------

// The digest with only what it uses.

final class SalesSummary
{
    /**
     * @param  list<int>  $sales
     */
    public function __construct(private readonly array $sales) {}

    public function total(): int
    {
        return array_sum($this->sales);
    }
}
```

### useless-property-hook

A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax

```php
----------[ Bad ]----------

// Implements the contract by copying the interface's hook syntax — but neither body reads
// `$this`, so both are stored properties wearing computed syntax: the constant belongs in a
// property default, the constructed value in the constructor.

final class TileAnimation implements AnimatedTile
{
    public ?string $enterEffect { get => null; }

    public ?string $leaveEffect {
        get => implode('+', ['fade', 'morph']);
    }

    public function __construct(private readonly string $tileId) {}
}

----------[ Good ]----------

// The FIX for {@see TileAnimation}: the same tile with both hooks made STORED properties — the
// constant body became a property default, the constructed one is assigned ONCE in the constructor.
// A plain property satisfies the interface's `{ get; }` just as well as a hook did.

final class StoredTileAnimation implements AnimatedTile
{
    public ?string $enterEffect = null;

    public ?string $leaveEffect;

    public function __construct(private readonly string $tileId)
    {
        $this->leaveEffect = implode('+', ['fade', 'morph']);
    }

    public function tileId(): string
    {
        return $this->tileId;
    }
}
```
