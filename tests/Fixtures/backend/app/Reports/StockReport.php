<?php

namespace Shop\Reports;

final class StockReport
{
    public function rows(string $warehouse): array
    {
        return [$warehouse, 'on hand', '12'];
    }

    public function toCsv(array $rows): string
    {
        return implode(',', $rows);
    }
}
