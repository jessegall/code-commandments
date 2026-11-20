<?php

namespace Shop\Tills;

use Illuminate\Contracts\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Held for the whole run by ReceiptHeaders, so it outlives every request too, and thanks each receipt's cashier by
 * reading the one on duty when the receipt prints.
 */
final class ReceiptFooters
{
    public function __construct(private readonly Container $container) {}

    #[Righteous(ContainerReach::class)]
    public function footer(): string
    {
        return 'Thank you for shopping with ' . $this->container->make(CashierOnDuty::class)->name;
    }
}
