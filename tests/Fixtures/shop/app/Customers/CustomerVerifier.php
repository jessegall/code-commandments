<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Detectors\Backend\MassUpdateAtCallSiteDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;

/**
 * Marks a customer verified by poking columns through a call-site mass update.
 */
final class CustomerVerifier
{
    public function __construct(private readonly string $now) {}

    #[Sinful(MassUpdateAtCallSiteDetector::class)]
    public function verify(Customer $customer): void
    {
        $customer->update([
            'verified' => true,
            'verified_at' => $this->now,
        ]);
    }
}
