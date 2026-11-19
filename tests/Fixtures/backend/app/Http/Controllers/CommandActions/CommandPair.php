<?php

namespace Shop\Http\Controllers\CommandActions;

use Illuminate\Foundation\Http\FormRequest;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRouteAction;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Pausing and resuming a subscription each build their own command and hand it to the one runner that turns any
 * command's outcome into a response: two operations through one dispatcher, not one operation reached twice.
 */
class SubscriptionRequest extends FormRequest {}

final class PauseSubscription
{
    public function __construct(public readonly int $subscription) {}
}

final class ResumeSubscription
{
    public function __construct(public readonly int $subscription, public readonly bool $chargeNow) {}
}

final class CommandRunner
{
    public function run(object $command): string
    {
        return $command::class . ' done';
    }
}

final class PauseSubscriptionController
{
    public function __construct(private readonly CommandRunner $runner) {}

    #[Righteous(DuplicateRouteAction::class)]
    public function pause(SubscriptionRequest $request): string
    {
        return $this->runner->run(new PauseSubscription($request->integer('subscription')));
    }
}

final class ResumeSubscriptionController
{
    public function __construct(private readonly CommandRunner $runner) {}

    #[Righteous(DuplicateRouteAction::class)]
    public function resume(SubscriptionRequest $request): string
    {
        return $this->runner->run(new ResumeSubscription($request->integer('subscription'), chargeNow: true));
    }
}
