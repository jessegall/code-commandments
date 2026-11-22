<?php

namespace Shop\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Events\LoyaltyCardIssued;
use Shop\Listeners\NotifyShelfStaff;

/**
 * Wires the card listener to an event the loyalty card model raises through its event map.
 */
#[Righteous(DeadEventWiring::class)]
final class LoyaltyEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        LoyaltyCardIssued::class => [NotifyShelfStaff::class],
    ];
}
