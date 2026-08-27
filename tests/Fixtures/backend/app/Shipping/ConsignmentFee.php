<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

interface Freightable
{
    public function weightGrams(): int;
}

/**
 * What the caller actually wants to know. Each kind of freight answers it for itself, so pricing a
 * new kind adds a class rather than a branch.
 */
#[Fixed(TypeSwitch::class)]
interface PricedFreight
{
    public function priceCents(): int;
}

final class ExpressFreight implements Freightable, PricedFreight
{
    public function weightGrams(): int
    {
        return 900;
    }

    #[Fixed(TypeSwitch::class)]
    public function priceCents(): int
    {
        return $this->weightGrams() * 12 + 500;
    }
}

final class PalletFreight implements Freightable, PricedFreight
{
    public function weightGrams(): int
    {
        return 240_000;
    }

    public function pallets(): int
    {
        return 3;
    }

    #[Fixed(TypeSwitch::class)]
    public function priceCents(): int
    {
        return $this->pallets() * 4_000;
    }
}

/**
 * Prices a consignment. The sinful version asks the freight what it IS and prices it from
 * outside; the righteous twin (`priceTold`) asks the freight what it COSTS, because each kind
 * already knows.
 */
final class ConsignmentFee
{
    #[Sinful(TypeSwitch::class)]
    public function price(Freightable $freight): int
    {
        if ($freight instanceof ExpressFreight) {
            return $freight->weightGrams() * 12 + 500;
        } elseif ($freight instanceof PalletFreight) {
            return $freight->pallets() * 4_000;
        }

        return $freight->weightGrams() * 3;
    }

    /**
     * The FIX: one method on the shared interface, implemented per freight type. Each kind answers
     * for itself, so a new kind needs no edit here at all.
     */
    #[Fixed(TypeSwitch::class)]
    #[Righteous(TypeSwitch::class)]
    public function priceTold(PricedFreight $freight): int
    {
        return $freight->priceCents();
    }
}
