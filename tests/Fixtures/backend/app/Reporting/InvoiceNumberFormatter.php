<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\CeremonyDocblock;

use JesseGall\CodeCommandments\Testing\Sinful;

final class InvoiceNumberFormatter
{
    private string $prefix = 'INV';

    /**
     * @param  int  $sequence
     * @return  string
     */
    #[Sinful(CeremonyDocblock::class)]
    public function format(int $sequence): string
    {
        return sprintf('%s-%06d', $this->prefix, $sequence);
    }

    public function withPrefix(string $prefix): self
    {
        $clone = clone $this;
        $clone->prefix = $prefix;

        return $clone;
    }
}
