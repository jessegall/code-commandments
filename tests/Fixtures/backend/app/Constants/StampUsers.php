<?php

namespace Shop\Constants;

final class StampUsers
{
    public function receipt(Stamp $stamp): string
    {
        return 'Paid ' . $stamp->label('Y-m-d');
    }

    /**
     * @return array<string, string>
     */
    public function export(Stamp $stamp, string $sku): array
    {
        return ['sku' => $sku, 'day' => $stamp->label('Y-m-d')];
    }

    public function heading(Stamp $stamp): string
    {
        return strtoupper($stamp->label('Y-m-d'));
    }
}
