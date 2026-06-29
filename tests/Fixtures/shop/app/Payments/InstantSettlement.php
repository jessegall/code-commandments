<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Detectors\Backend\EnumCaseOrChainDetector;
use Shop\Enums\PaymentMethod;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Decides whether a payment clears instantly. The eligible set — card and iDEAL —
 * is re-derived inline instead of living as a method on PaymentMethod.
 */
final class InstantSettlement
{
    public function __construct(private readonly int $retries = 0) {}

    #[Sinful(EnumCaseOrChainDetector::class)]
    public function clearsImmediately(PaymentMethod $method): bool
    {
        if ($this->retries > 3) {
            return false;
        }

        return $method === PaymentMethod::Card || $method === PaymentMethod::Ideal;
    }
}
