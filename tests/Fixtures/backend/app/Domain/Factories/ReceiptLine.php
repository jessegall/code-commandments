<?php

namespace Shop\Domain\Factories;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedStaticFactory;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

final class ReceiptLine
{
    #[Sinful(RepeatedStaticFactory::class)]
    public function describe(int $cents, string $currency, int $quantity): string
    {
        $unit = Price::of($cents, $currency)->cents();
        $total = $unit * $quantity;

        return sprintf('%d x %d %s = %d', $quantity, $unit, Price::of($cents, $currency)->currency(), $total);
    }

    #[Fixed(RepeatedStaticFactory::class)]
    public function describeOnce(int $cents, string $currency, int $quantity): string
    {
        $price = Price::of($cents, $currency);

        return sprintf('%d x %d %s = %d', $quantity, $price->cents(), $price->currency(), $price->cents() * $quantity);
    }
}
