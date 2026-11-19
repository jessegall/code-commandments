<?php

namespace Shop\Imports;

/**
 * The file layouts the product import accepts.
 */
final class ImportFormatBook
{
    /**
     * @param  list<class-string<ImportFormat>>  $formats
     */
    private function __construct(public readonly array $formats) {}

    /**
     * @param  list<class-string<ImportFormat>>  $formats
     */
    public static function of(array $formats): self
    {
        return new self($formats);
    }
}
