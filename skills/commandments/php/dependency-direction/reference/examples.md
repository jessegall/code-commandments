# Dependency direction — a layer may only reach DOWN — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### namespace-cycle

two namespaces that reference each other — neither can be read, tested or moved alone

```php
----------[ Bad ]----------

public function firstClaim(): Claim
{
    return new Claim($this->months);
}

----------[ Good ]----------

// in Shop\Warranty\WarrantyPolicy
public function coveredMonthsOf(CoverageClaim $claim): int
{
    return min($claim->claimedMonths(), $this->months);
}

final class Claim implements CoverageClaim
{
    public function __construct(public readonly int $coveredMonths) {}

    public function claimedMonths(): int
    {
        return $this->coveredMonths;
    }

    public function policy(): WarrantyPolicy
    {
        return new WarrantyPolicy($this->coveredMonths);
    }
}
```

### namespace-dependency

a declared layer references a layer it may not use (the arrow points back up)

```php
----------[ Bad ]----------

public function inPanel(): Panel
{
    return new Panel($this->label);
}

----------[ Good ]----------

// in Shop\Ui\Elements\Badge
public function accent(): Accent
{
    return new Accent($this->label);
}

final class Accent
{
    public function __construct(public readonly string $name) {}

    public function hex(): string
    {
        return $this->name === 'primary' ? '#1b5e20' : '#616161';
    }
}
```
