<?php

namespace Shop\Support;

/**
 * Reads fields out of a parsed CSV row by position.
 */
final class CsvLine
{
    /**
     * @param  list<string>  $columns
     */
    public function field(array $columns, int $index): string
    {
        return $columns[$index] ?? '';
    }

    /**
     * @param  list<string>  $columns
     */
    public function first(array $columns): string
    {
        return $columns[0] ?? '';
    }
}
