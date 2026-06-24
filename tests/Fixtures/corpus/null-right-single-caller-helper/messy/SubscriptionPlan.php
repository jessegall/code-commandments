<?php

namespace App\OptionCorpus\NullRightSingleCallerHelper\Messy;

/** A billing plan with an optional trial window. */
final readonly class SubscriptionPlan
{
    public function __construct(
        public string $name,
        public int $monthlyCents,
        public ?int $trialDays = null,
    ) {}
}
