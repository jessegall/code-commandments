<?php

namespace Shop\InterfaceOverlap\Invoices;

interface InvoiceReader
{
    public function exists(int $invoice): bool;
    public function total(int $invoice): int;
}
