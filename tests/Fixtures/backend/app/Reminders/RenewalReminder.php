<?php

namespace Shop\Reminders;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Warranty\WarrantyPolicy;

/**
 * Nudges a customer before their cover lapses. It reads the warranty terms freely — and the
 * warranty knows nothing of reminders, so the arrow points ONE way and either side can still be
 * lifted out on its own. A dependency is not a cycle.
 */
#[Righteous(NamespaceCycle::class)]
final class RenewalReminder
{
    public function dueInDays(WarrantyPolicy $policy): int
    {
        return $policy->months * 30 - 14;
    }
}
