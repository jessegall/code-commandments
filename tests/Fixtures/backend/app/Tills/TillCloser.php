<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\PhpTypes\OptionAsNullable;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\PhpTypes\Option;

/**
 * Closes a till for the night, noting the cashier who counted it when one did.
 */
final class TillCloser
{
    /**
     * @param  Option<string>  $countedBy
     */
    #[Sinful(OptionAsNullable::class)]
    public function close(string $till, Option $countedBy): string
    {
        return $this->closingLine($till, $countedBy->unwrapOr(null));
    }

    private function closingLine(string $till, ?string $cashier): string
    {
        return $cashier === null ? "{$till} closed uncounted" : "{$till} closed, counted by {$cashier}";
    }
}
