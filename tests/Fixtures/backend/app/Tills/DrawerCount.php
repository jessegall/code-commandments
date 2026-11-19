<?php

namespace Shop\Tills;

use Illuminate\Contracts\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Counts a drawer for the request at hand: it is made per request like the cashier it reads, so nothing stops the
 * cashier being injected, and reaching into the container only hides the dependency.
 */
final class DrawerCount
{
    public function __construct(private readonly Container $container) {}

    #[Sinful(ContainerReach::class)]
    public function signedOff(int $cents): string
    {
        return sprintf('%d cents counted by %s', $cents, $this->container->make(CashierOnDuty::class)->name);
    }
}
