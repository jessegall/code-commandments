<?php

namespace Shop\Loyalty;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A loyalty member. `$tier` is typed `?LoyaltyTier`, but the value surfaces through the account
 * summary, the profile card, the dashboard header and finally the badge — five files of view models
 * that each just return it — where it is read as present with no null-guard anywhere.
 */
#[Sinful(PhantomNullable::class)]
final class Member
{
    public function __construct(
        private readonly ?LoyaltyTier $tier,
        private readonly string $email,
    ) {}

    public function currentTier(): ?LoyaltyTier
    {
        return $this->tier;
    }
}
