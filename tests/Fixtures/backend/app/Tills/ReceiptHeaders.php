<?php

namespace Shop\Tills;

use Illuminate\Contracts\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Bound once for the whole run in TillServiceProvider, it prints each receipt's header with the cashier of the request
 * at hand: injecting that cashier would keep the first request's for good, so it is read per receipt.
 */
final class ReceiptHeaders
{
    public function __construct(private readonly Container $container) {}

    #[Righteous(ContainerReach::class)]
    public function header(int $receipt): string
    {
        return sprintf('Receipt %d, served by %s', $receipt, $this->container->make(CashierOnDuty::class)->name);
    }
}
