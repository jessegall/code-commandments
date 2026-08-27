# Dependency direction — a layer may only reach DOWN — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### namespace-cycle

two namespaces that reference each other — neither can be read, tested or moved alone

```php
----------[ Bad ]----------

// Claims reads the policy from two places; this is the single arrow pointing back, and it is
// what stops the warranty terms from being read, tested, or reused without the claims desk.

public function firstClaim(): Claim
{
    return new Claim($this->months);
}

----------[ Good ]----------

// in Shop\Warranty\WarrantyPolicy
// The FIX: the arrow back into Claims is gone. The policy states its side of the relationship as a
// contract it OWNS (`CoverageClaim`), the claims desk implements it, and Warranty can now be read,
// tested and lifted out with nothing from the desk coming along.

public function coveredMonthsOf(CoverageClaim $claim): int
{
    return min($claim->claimedMonths(), $this->months);
}

// One warranty claim raised against a policy — and the claims desk's side of the cut: it implements
// the contract Warranty declared, so the only arrow between the two runs Claims → Warranty.

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

// The arrow pointing back up: Elements is the bottom of the stack, yet this one hands out a
// Panel — the thing Shared assembles FROM badges. Elements can no longer be read, reused or
// moved without Shared coming along.

public function inPanel(): Panel
{
    return new Panel($this->label);
}

----------[ Good ]----------

// in Shop\Ui\Elements\Badge
// The FIX: the arrow inverted. The badge hands out only what it owns — a token from the layer
// BELOW it — and Shared assembles the panel from that. Elements names nothing above itself, so it
// reads, tests and moves on its own again.

public function accent(): Accent
{
    return new Accent($this->label);
}

// A design token — the very bottom of the UI stack, which knows about nothing above it.

final class Accent
{
    public function __construct(public readonly string $name) {}

    public function hex(): string
    {
        return $this->name === 'primary' ? '#1b5e20' : '#616161';
    }
}
```
