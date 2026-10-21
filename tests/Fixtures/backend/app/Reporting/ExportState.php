<?php

namespace Shop\Reporting;

/**
 * What one export run came to. The counted rows are handed to the constructor; the field above
 * them is left writable for the loader that fills it in after the fact.
 */
final class ExportState
{
    public int $rows = 0;

    public function __construct(int $rows = 0)
    {
        $this->rows = $rows;
    }

    public function isEmpty(): bool
    {
        return $this->rows === 0;
    }
}
