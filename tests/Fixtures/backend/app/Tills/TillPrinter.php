<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Prints the framed lines at the top and bottom of a till's receipt roll.
 */
final class TillPrinter
{
    #[Righteous(ConvertedArgument::class)]
    public function header(int $till): string
    {
        return $this->framed(TillCaption::for('opened', $till));
    }

    #[Righteous(ConvertedArgument::class)]
    public function footer(int $till): string
    {
        return $this->framed(TillCaption::for('closed', $till));
    }

    private function framed(string $text): string
    {
        return "| {$text} |";
    }
}
