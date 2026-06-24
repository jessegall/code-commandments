<?php

namespace App\OptionCorpus\NullRightSingleCallerHelper\Golden;

use Illuminate\Support\Carbon;

/** Builds the customer-facing checkout summary for a chosen plan. */
final readonly class CheckoutSummary
{
    public function __construct(
        private Carbon $now,
    ) {}

    public function for(SubscriptionPlan $plan): array
    {
        // The ONE caller of the private helper. A single clean `?? default`:
        // when the plan has no trial we just bill from today. Nothing is
        // threaded, mapped, or re-coalesced — absence means "starts now".
        $billingStartsOn = $this->trialEndsOn($plan) ?? $this->now;

        return [
            'plan' => $plan->name,
            'price' => $plan->monthlyCents / 100,
            'billing_starts_on' => $billingStartsOn->toDateString(),
        ];
    }

    /**
     * Thin, private. Returns the trial end date, or null when there is no trial.
     * Absence is just "not set" — not a domain event the caller juggles.
     */
    private function trialEndsOn(SubscriptionPlan $plan): ?Carbon
    {
        if ($plan->trialDays === null) {
            return null;
        }

        return $this->now->copy()->addDays($plan->trialDays);
    }
}
