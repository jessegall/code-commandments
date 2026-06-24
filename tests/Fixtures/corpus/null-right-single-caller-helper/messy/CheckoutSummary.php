<?php

namespace App\OptionCorpus\NullRightSingleCallerHelper\Messy;

use Illuminate\Support\Carbon;
use JesseGall\PhpTypes\Option;

/** Builds the customer-facing checkout summary for a chosen plan. */
final readonly class CheckoutSummary
{
    public function __construct(
        private Carbon $now,
    ) {}

    public function for(SubscriptionPlan $plan): array
    {
        // NEEDLESS Option: the helper builds an Option, and the single caller
        // immediately ->getOrElse-s it straight back to a Carbon. The wrapper
        // does nothing the bare `?? $this->now` didn't already do — pure
        // ceremony for one local `?? default` call site.
        $billingStartsOn = $this->trialEndsOn($plan)->getOrElse($this->now);

        return [
            'plan' => $plan->name,
            'price' => $plan->monthlyCents / 100,
            'billing_starts_on' => $billingStartsOn->toDateString(),
        ];
    }

    /**
     * Over-engineered: wraps a trivial nullable in Option for a single caller
     * that unwraps it on the very next line. No map/chain, no second consumer.
     *
     * @return Option<Carbon>
     */
    private function trialEndsOn(SubscriptionPlan $plan): Option
    {
        if ($plan->trialDays === null) {
            return Option::none();
        }

        return Option::some($this->now->copy()->addDays($plan->trialDays));
    }
}
