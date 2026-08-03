<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Sins\Backend\MutableValueObject;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A markup that changes itself when discounted — so a markup handed to two callers can become a
 * different markup under one of them, and neither wrote the code that did it.
 */
#[Sinful(MutableValueObject::class)]
final class Markup
{
    public function __construct(private float $percentage) {}

    public function discountBy(float $points): void
    {
        $this->percentage -= $points;
    }

    public function appliedTo(int $cents): int
    {
        return (int) round($cents * (1 + $this->percentage / 100));
    }
}

/**
 * The same markup, derived instead of changed: discounting answers with a NEW markup and leaves
 * every existing holder of this one with exactly what they were given.
 */
#[Righteous(MutableValueObject::class)]
final class DerivedMarkup
{
    public function __construct(private readonly float $percentage) {}

    public function discountedBy(float $points): self
    {
        return new self($this->percentage - $points);
    }

    public function appliedTo(int $cents): int
    {
        return (int) round($cents * (1 + $this->percentage / 100));
    }
}
