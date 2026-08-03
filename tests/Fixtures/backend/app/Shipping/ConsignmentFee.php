<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

interface Freightable
{
    public function weightGrams(): int;
}

final class ExpressFreight implements Freightable
{
    public function weightGrams(): int
    {
        return 900;
    }
}

final class PalletFreight implements Freightable
{
    public function weightGrams(): int
    {
        return 240_000;
    }

    public function pallets(): int
    {
        return 3;
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

    #[Righteous(TypeSwitch::class)]
    public function priceTold(PricedFreight $freight): int
    {
        return $freight->priceCents();
    }
}

interface PricedFreight
{
    public function priceCents(): int;
}
