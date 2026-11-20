<?php

namespace Shop\InterfaceOverlap\Invoices;

use JesseGall\CodeCommandments\Sins\Shared\InterfaceOverlap;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

#[Sinful(InterfaceOverlap::class)]
interface ReservedInvoiceReader
{
    public function exists(int $invoice): bool;
    public function total(int $invoice): int;
}

#[Righteous(InterfaceOverlap::class)]
interface WholesaleInvoiceReader
{
    public const PRICE_LIST = 'wholesale';

    public function exists(int $invoice): bool;
    public function total(int $invoice): int;
}
