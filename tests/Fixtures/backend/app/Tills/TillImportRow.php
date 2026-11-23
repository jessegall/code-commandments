<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * One row of an imported till sheet, read by column; a blank or missing cell is no value.
 */
final class TillImportRow
{
    /**
     * @param  array<string, string|null>  $cells
     */
    public function __construct(private readonly array $cells) {}

    #[Righteous(NullableRegistryLookup::class)]
    public function cell(string $column): ?string
    {
        return $this->cells[$column] ?? null;
    }
}
