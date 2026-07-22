<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Contracts\SmsGateway;

/*
 * Righteous twin for ContainerReach — an abstract context whose SUBCLASSES are hand-`new`ed
 * (the base itself never is). The container never fills its constructor, so a memoised
 * per-call resolution is its only seam to a process-wide service; there is no injection
 * point to move the dependency to (#392). Must NOT flag.
 */
#[Righteous(ContainerReach::class)]
abstract class DispatchContext
{
    public function gateway(): SmsGateway
    {
        return app(SmsGateway::class);
    }

    abstract public function carrier(): string;
}

final class ParcelDispatchContext extends DispatchContext
{
    public function carrier(): string
    {
        return 'postnl';
    }
}

final class DispatchRunner
{
    public function run(): string
    {
        return (new ParcelDispatchContext)->carrier();
    }
}
