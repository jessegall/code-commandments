<?php

namespace Shop\Shelving;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;

use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Righteous twin for ConvertedArgument: `BayRef::from()` is a boundary DECODE feeding a parameter typed
 * as the OBJECT. Moving it into the callee would leave `describe()` taking a raw string — the rule
 * inverted, primitive obsession bought with a refactor.
 */
final class BayCatalogue
{
    #[Righteous(ConvertedArgument::class)]
    public function fromSheet(string $raw): string
    {
        return $this->describe(BayRef::from($raw));
    }

    #[Righteous(ConvertedArgument::class)]
    public function fromScan(string $scanned): string
    {
        return $this->describe(BayRef::from($scanned));
    }

    private function describe(BayRef $bay): string
    {
        return $bay->label();
    }
}
