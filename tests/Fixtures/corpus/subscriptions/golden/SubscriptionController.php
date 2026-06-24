<?php

declare(strict_types=1);

namespace App\Subscriptions;

use Illuminate\Http\RedirectResponse;

/**
 * Accepts a request to start a subscription.
 */
final class SubscriptionController
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        $this->subscriptions->start(new SubscriptionData(
            customerId: $request->customerId(),
            plan: $request->plan(),
            cycle: $request->cycle(),
        ));

        return redirect()->route('subscriptions.index');
    }
}
