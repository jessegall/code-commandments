<?php

namespace Shop\Shipping\Labels;

use JesseGall\CodeCommandments\Sins\Backend\UnreachableStatement;
use JesseGall\CodeCommandments\Sins\Backend\UnusedParameter;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Lays out the text lines of a shipping label for a carrier's printer.
 */
class LabelPrinter
{
    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function layout(string $carrier, string $locale, array $lines): array
    {
        return [$this->header($carrier, $locale), ...array_map($this->numbered(...), $lines, array_keys($lines))];
    }

    #[Sinful(UnreachableStatement::class)]
    public function copies(string $carrier): int
    {
        foreach (['dhl', 'ups'] as $twoCopies) {
            if ($carrier === $twoCopies) {
                return 2;
                continue;
            }
        }

        return 1;
    }

    #[Sinful(UnusedParameter::class)]
    private function header(string $carrier, string $locale): string
    {
        return str_pad(strtoupper($carrier), 40, '=', STR_PAD_BOTH);
    }

    #[Righteous(UnusedParameter::class)]
    private function numbered(string $line, int $position): string
    {
        return $line;
    }
}
