<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Testing\Sinful;

final class CurrencyFormatter
{
    #[Sinful(ConfigRead::class)]
    public function format(int $cents, string $currency): string
    {
        $symbol = app('config')->get("shop.symbols.{$currency}", '$');

        return $symbol . number_format($cents / 100, 2);
    }
}
